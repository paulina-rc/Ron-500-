# Rumi Quinientos — contexto del proyecto

## Qué es

Juego web multijugador de **Rumi Quinientos**, la variante costarricense del Rummy 500.
Dos jugadores conectados desde computadora y teléfono.

> **IMPORTANTE:** las reglas están en `REGLAS-RUMI-500-v2.md` y **no son** las del
> Rummy 500 clásico. Leé ese archivo antes de tocar cualquier lógica del juego.
> No asumas reglas estándar: los valores, el descarte y los embones son distintos.

## Stack

- **Laravel** (PHP). Verificar primero la versión de PHP disponible en cPanel.
- **MySQL** para el estado.
- **Frontend:** Blade + JavaScript sin framework pesado. Debe funcionar en móvil.
- **Tiempo real:** polling. Ver la sección de restricciones.

## Restricciones del hosting (cPanel compartido)

Estas condicionan la arquitectura entera. No las ignores:

1. **No hay procesos persistentes.** Nada de WebSockets propios, Laravel Reverb,
   colas con worker permanente ni `artisan serve`. PHP responde y muere.
2. **El estado del juego NO puede vivir en memoria.** Va en MySQL, serializado como
   JSON en una columna. Cada petición lo lee, lo modifica y lo vuelve a guardar.
3. **Sincronización por polling.** El cliente pregunta cada ~1.5 s si hubo cambios.
   Para que sea barato, el estado lleva un contador `version` que sube con cada
   jugada. El cliente manda el que tiene; si no cambió, el servidor responde `204`
   sin cuerpo.
4. El *document root* del dominio tiene que apuntar a `/public` de Laravel.

## Estructura

```
/app/Domain/Rumi/     Motor de reglas en PHP puro. Sin Eloquent, sin HTTP.
/app/Models/          Sala, Jugador, Partida
/app/Http/            Controladores delgados: validan, llaman al motor, guardan
/resources/views/     Blade
/resources/js/        Cliente: render de mesa, polling, acciones
/tests/Unit/Rumi/     Pruebas del motor. Obligatorias.
```

El motor va **aislado** en `Domain/Rumi`: recibe un estado y una acción, devuelve un
estado nuevo o lanza excepción. No sabe de bases de datos ni de peticiones. Así se
puede probar con PHPUnit sin levantar nada.

## Convenciones

- Mensajes de error **en español**, dirigidos al jugador ("No es tu turno",
  "Tenés que usar la carta que comiste de la basura").
- Las acciones ilegales lanzan una excepción propia, no devuelven `false`.
- **El servidor es la única fuente de verdad.** A cada cliente se le manda solo su
  propia mano y la *cantidad* de cartas del rival. Nunca la mano ajena: si va en el
  JSON, el rival la lee en DevTools.
- Cada carta necesita **id único**: se juega con dos barajas y hay duplicados
  exactos. Formato `7H#1`, `7H#2`.
- Cada carta puesta en mesa guarda **quién la puso**. Los puntos de un embone son de
  quien lo puso, no del dueño de la jugada.
- **La mano no se ordena sola.** El jugador la acomoda y ese orden se respeta.
  Las cartas comidas se agregan al final.
- Antes de dejar comer de la basura, validar que exista jugada legal para la carta
  obligatoria. Validar **antes** de modificar el estado, para no dejarlo a medias.

## Orden de trabajo

1. Motor de reglas en `Domain/Rumi` + pruebas en PHPUnit
2. Modelos y migraciones: salas, jugadores, partida
3. Endpoints: crear sala, unirse, ver estado, ejecutar acción
4. Interfaz: mesa, mano, basura escalonada, juegos bajados
5. Polling y reconexión
6. Deploy en cPanel

No avanzar a la 2 sin que las pruebas de la 1 estén verdes.

### Estado actual (noviembre 2026)

- [x] 1–4. Motor, persistencia, API e interfaz con acciones
- [x] Rondas y partida a 500: `POST /api/salas/{sala}/siguiente-ronda` (idempotente,
      alterna quién empieza), marcador acumulado, hoja de fin de ronda y de partida.
      Supuestos: empieza el otro jugador cada ronda; empate exacto arriba de 500 = otra ronda.
- [x] 5. Reconexión: sesión en localStorage + enlace personal `/salas/ABCD#t=TOKEN`;
      invitación `/?unirse=ABCD`
- [ ] 6. Deploy en cPanel (falta: sacar `bootstrap/cache/*.php` del repo, guía de subida,
      MySQL, migraciones)

Para probar localmente: `composer install`, `cp .env.example .env`,
`php artisan key:generate`, `php artisan migrate`, `php artisan serve`.
Se prueba con dos navegadores distintos (o uno en incógnito), porque la sesión
de cada jugador vive en localStorage.

## Referencia del motor

Existe una implementación de referencia **en JavaScript**, ya probada, con 35 pruebas
que cubren todas las reglas. Sirve como especificación: al portar a PHP, portá también
esas pruebas y verificá que dan el mismo resultado. No la copies literal — el estilo
debe ser PHP idiomático — pero los casos de prueba deben quedar todos.

## Qué NO hacer

- No implementar reglas del Rummy 500 clásico "porque son las estándar".
- No mandar información al cliente que el jugador no debería ver.
- No ordenar la mano automáticamente.
- No meter lógica de reglas en los controladores ni en el JavaScript del cliente.
  El cliente puede *sugerir* jugadas, pero quien decide es el servidor.
- No usar WebSockets ni nada que necesite un proceso corriendo.