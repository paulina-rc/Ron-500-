# Rumi Quinientos — Especificación de reglas (v2)

> Versión limpia con las respuestas integradas.
> Los pendientes nuevos están al final, sección 11.

---

## 1. Objetivo

Acumular puntos hasta llegar a **500**. Gana el primero que los alcance.

Si en la misma ronda ambos jugadores pasan de 500, gana **el que tenga más puntos**.

---

## 2. Baraja y valor de las cartas

- Se juega con **dos barajas** de naipes → **104 cartas**.
- **Sin comodines / jokers.**

| Carta | Puntos |
|---|---|
| A | 15 |
| 2 | 20 (excepción) |
| 3, 4, 5, 6, 7, 8, 9 | 5 |
| 10, J, Q, K | 10 |

**Gane:** el jugador que cierra la ronda recibe **+20 puntos** extra.

---

## 3. Reparto

- **12 cartas** por jugador.
- Las cartas **NO se ordenan automáticamente**. El jugador las acomoda como quiera y ese orden se conserva.
- El resto forma el **mazo**.
- Se voltea **una carta** del mazo para iniciar la basura.

---

## 4. El turno

Tres pasos **obligatorios en orden**:

1. **Comer** — una carta del mazo, **o** cartas de la basura (sección 5).
2. **Bajar / embonar** — opcional (secciones 6 y 7).
3. **Botar** — una carta a la basura. Siempre. El turno no termina sin descarte.

Solo se puede comer **en el propio turno**.

---

## 5. Basura abierta

La basura se despliega **escalonada**: cada carta se pone encima de la anterior pero **todas quedan visibles**, y se puede comer desde cualquier posición.

### Regla al comer de la basura

- Me llevo **obligatoriamente todas las cartas que están encima** de la que quiero.
- La carta **desde donde agarré** debe usarse **en mesa ese mismo turno**. No puede quedarse en mi mano.
- Las cartas de encima **sí** pueden quedarse en mi mano.
- Esto aplica **también** si tomo solo la carta de encima de la basura: igual estoy obligado a usarla.

**Ejemplo:** necesito el 2. Encima del 2 hay un 3 y un 5.
→ Me llevo el 2, el 3 y el 5. El **2 tiene que salir a mesa** este turno. El 3 y el 5 se quedan en mi mano si quiero.

### La carta obligatoria puede ir como embone

La carta obligatoria puede usarse en un juego nuevo mío **o como embone a un juego ajeno**. De hecho el propósito del embone es ese.

**Consecuencia importante:** como para embonar hay que **haberse bajado antes** (sección 6), si todavía no me he bajado, la carta obligatoria **tiene que ir en un juego nuevo mío**.

→ Regla para el motor: **solo puedo comer de la basura si existe una jugada legal donde colocar la carta obligatoria.** El servidor valida esto antes de permitir la acción.

---

## 6. Juegos válidos

### Trío / set
- **3 o 4 cartas** del mismo número.
- **De palos distintos** — no se repite palo dentro del mismo juego.

### Escalera
- **Mínimo 3 cartas**, del **mismo palo**, en secuencia.
- El As funciona como **1** (`A-2-3`) **o** como **14** (`Q-K-A`).
- La escalera **no da la vuelta**: `K-A-2` no es válida. El As es una cosa o la otra, nunca las dos en la misma escalera.

### Reglas de bajada
- **No hay requisito mínimo** para bajarse. Se puede bajar desde el primer turno.
- Se puede bajar **cuantos juegos se quiera** en un mismo turno.
- Se puede bajar y embonar **en el mismo turno**.

### Requisito para embonar
Para poder embonar hay que **haberse bajado antes** con al menos una jugada propia (trío o escalera).

---

## 7. Embone y propiedad de las cartas

Un **embone** es una carta que se agrega a un juego que ya está en la mesa, propio o ajeno.

**Regla clave:** aunque la carta complete el juego de otro jugador, **los puntos son de quien la puso**.

### Cómo se ve en pantalla

La carta embonada **se pega visualmente al juego al que pertenece** (no queda suelta al lado), pero lleva una **marca de color** que indica de quién es.

```
Juego #1 (bajado por Paulina): [7♥ P] [7♠ P] [7♦ P] [7♣ C]
                                                     ↑
                                          embone del compañero,
                                          marcado en su color;
                                          sus 5 pts van a él
```

### Cómo se implementa

Cada carta en mesa guarda su **dueño** (quién la bajó), independiente de a qué juego pertenece. Al puntear, cada carta suma **al dueño de la carta**, no al dueño del juego.

### Con 3 o más jugadores
- Si solo hay **un** juego compatible → se asigna automático.
- Si hay **varios** → se le pregunta al jugador a cuál va.

---

## 8. Fin de ronda

La ronda termina de dos formas:

**A) Un jugador sale.**
Se queda sin cartas en la mano **y bota una carta a la basura**. Es obligatorio botar: no se puede salir bajando todo. La mano queda en 0 **después** del descarte.
→ Ese jugador recibe el **gane: +20 pts**.

**B) Se acaba el mazo y nadie ha salido.**
La ronda termina ahí. **No se revuelve la basura.**
→ **Nadie recibe el gane.**

En ambos casos se puntea igual: mesa suma, mano resta.

---

## 9. Puntuación

```
Puntos de la ronda = suma de las cartas que puso en mesa
                   + 20 si cerró la ronda
                   - suma de las cartas que le quedaron en mano
```

El resultado de una ronda **puede ser negativo**.

---

## 10. Alcance por versiones

### v1 — Jugable
- 2 jugadores, sala con código
- Motor completo de reglas
- Interfaz responsive (tap, no drag)
- Reconexión al refrescar o cambiar de app

### v2 — Más gente
- 3+ jugadores
- Marca de color por dueño de carta
- Selección de juego destino al embonar

### v3 — Extras
- Cuentas de usuario y niveles
- Bot
- Diseño propio de cartas
- **Juego adicional: CULO** (otro juego de naipe, misma plataforma)

---

## 11. Resueltos

**[RESUELTO 10] Palo en los tríos.** El palo NO importa. Con dos barajas,
`7♥ 7♥ 7♠` es válido. Solo importa que sean 3 o más del mismo número.
No hay tope de tamaño: el máximo natural es 8 (ocho cartas de cada número
entre las dos barajas).

**[RESUELTO 11] Escaleras repetidas.** Permitidas. Dos jugadores pueden
tener la misma escalera en mesa.

**[RESUELTO 12] Largo de escalera.** Tope natural de 13 cartas (A a K).

**[RESUELTO 13] Tamaño de mano.** 12 cartas, como dice la sección 3.
## 12. Notas para el motor

- El servidor es la única fuente de verdad: tiene el mazo y todas las manos.
- A cada cliente se le manda **solo su mano** + la cantidad de cartas del rival.
- La basura se manda completa a todos (es abierta y visible por regla).
- El orden de la mano lo define el jugador y se guarda en el cliente, no en el servidor.
- Cada carta necesita **id único** (hay duplicados exactos por las dos barajas): ej. `7H#1`, `7H#2`.
- Cada carta en mesa guarda `dueño`.
- Antes de permitir comer de la basura, validar que exista jugada legal para la carta obligatoria.
