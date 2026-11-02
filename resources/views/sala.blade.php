<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rumi Quinientos — Sala {{ $codigo }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/rumi.css') }}">
</head>
<body>
    <div class="pantalla" id="app" data-codigo="{{ $codigo }}">

        <div id="vista-sin-acceso" class="espera oculto">
            <span class="espera__etiqueta">Sin acceso</span>
            <p class="espera__ayuda">Este dispositivo no está dentro de la sala {{ $codigo }}. Si te invitaron, unite con tu nombre. Si ya estabas jugando, abrí tu enlace personal desde el otro dispositivo.</p>
            <a href="/?unirse={{ $codigo }}" class="boton">Unirme a esta sala</a>
            <a href="/" class="boton boton--fantasma">Volver al inicio</a>
        </div>

        <div id="vista-espera" class="espera oculto">
            <span class="espera__etiqueta">Compartí este código</span>
            <span class="espera__codigo" id="espera-codigo">{{ $codigo }}</span>
            <div class="pulso"></div>
            <button id="btn-invitar" class="boton">Compartir invitación</button>
            <p class="espera__ayuda">Esperando a que se una el otro jugador. Dejá esta pantalla abierta.</p>
        </div>

        <div id="vista-mesa" class="mesa oculto">
            <div class="cab">
                <div class="cab__marca">
                    <span class="marca">Rumi 500</span>
                    <button id="btn-sala" class="cab__sala" aria-label="Opciones de la sala">Sala {{ $codigo }} · <span id="cab-ronda">Ronda 1</span> ▾</button>
                </div>
                <div class="pill-turno" id="pill-turno">
                    <span class="pill-turno__punto" id="pill-turno-punto"></span>
                    <span class="pill-turno__texto" id="pill-turno-texto"></span>
                </div>
                <div class="marcadores">
                    <div class="marcador">
                        <span class="marcador__etiqueta" style="color:#E8A21C">VOS</span>
                        <span class="marcador__valor" id="marcador-yo">0</span>
                        <span class="marcador__ronda" id="marcador-yo-ronda" title="Puntos de la ronda en curso"></span>
                    </div>
                    <div class="marcador">
                        <span class="marcador__etiqueta" style="color:#8B6BE0" id="marcador-rival-nombre">RIVAL</span>
                        <span class="marcador__valor" id="marcador-rival">0</span>
                        <span class="marcador__ronda" id="marcador-rival-ronda" title="Puntos de la ronda en curso"></span>
                    </div>
                </div>
            </div>

            <div class="franja-rival">
                <span class="panel-titulo" id="rival-nombre">RIVAL</span>
                <div class="dorsos-rival" id="dorsos-rival"></div>
                <span id="rival-cantidad" style="font:400 9.5px/1 Archivo,sans-serif;color:rgba(247,246,242,.4)"></span>
            </div>

            <div class="cuerpo">
                <div class="panel-mesa">
                    <div class="panel-titulo">MESA</div>
                    <div class="jugadas" id="jugadas"></div>
                    <div class="vacio-mesa oculto" id="mesa-vacia">Todavía no hay jugadas en la mesa.</div>
                </div>
                <div class="panel-lateral">
                    <div class="bloque-lateral">
                        <span class="panel-titulo">MAZO</span>
                        <div id="mazo"></div>
                    </div>
                    <div class="bloque-lateral">
                        <span class="panel-titulo">BASURA</span>
                        <div class="basura-pila" id="basura"></div>
                    </div>
                </div>
            </div>

            <div class="pie">
                <div id="mensaje" class="mensaje"></div>
                <div class="fila-acciones">
                    <button id="btn-bajar" class="boton" disabled>Bajar</button>
                    <button id="btn-botar" class="boton boton--fantasma" disabled>Botar</button>
                    <div style="flex:1"></div>
                    <button id="btn-mover-izq" class="boton-mover" disabled aria-label="Mover a la izquierda">◀</button>
                    <button id="btn-mover-der" class="boton-mover" disabled aria-label="Mover a la derecha">▶</button>
                </div>
                <div class="fila-mano-titulo">
                    <span class="panel-titulo">TU MANO</span>
                    <span id="mano-cantidad" style="font:400 9.5px/1 Archivo,sans-serif;color:rgba(247,246,242,.4)"></span>
                </div>
                <div class="mano-scroll"><div class="mano" id="mano"></div></div>
            </div>
        </div>

        <div id="aviso-reconectar" class="aviso-reconectar oculto">Reconectando…</div>

        <div id="hoja-fondo" class="hoja-fondo oculto"></div>
        <div id="hoja-comer" class="hoja oculto">
            <div class="hoja__titulo">Comer de la basura</div>
            <div id="hoja-comer-detalle" class="hoja__detalle"></div>
            <div class="hoja__acciones">
                <button id="hoja-comer-cancelar" class="boton boton--fantasma">Cancelar</button>
                <button id="hoja-comer-confirmar" class="boton">Confirmar</button>
            </div>
        </div>

        <div id="hoja-sala" class="hoja oculto">
            <div class="hoja__titulo">Sala {{ $codigo }}</div>
            <div class="hoja__detalle">
                <div><strong>Tu enlace personal</strong> sirve para seguir jugando desde otro teléfono o navegador. No se lo pasés a nadie: con ese enlace se juega por vos.</div>
            </div>
            <div class="hoja__acciones">
                <button id="hoja-sala-cerrar" class="boton boton--fantasma">Cerrar</button>
                <button id="hoja-sala-copiar" class="boton">Copiar mi enlace</button>
            </div>
        </div>

        <div id="hoja-ronda" class="hoja hoja--ronda oculto">
            <div class="hoja__titulo" id="hoja-ronda-titulo"></div>
            <div class="hoja__detalle" id="hoja-ronda-subtitulo"></div>
            <table class="tabla-ronda">
                <thead>
                    <tr><th></th><th>Mesa</th><th>Gane</th><th>Mano</th><th>Ronda</th><th>Total</th></tr>
                </thead>
                <tbody id="hoja-ronda-filas"></tbody>
            </table>
            <div class="hoja__detalle" id="hoja-ronda-meta"></div>
            <div class="hoja__acciones">
                <button id="hoja-ronda-ver-mesa" class="boton boton--fantasma">Ver mesa</button>
                <button id="hoja-ronda-siguiente" class="boton">Siguiente ronda</button>
                <a id="hoja-ronda-inicio" class="boton oculto" href="/">Nueva partida</a>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/almacenamiento.js') }}"></script>
    <script src="{{ asset('js/api.js') }}"></script>
    <script src="{{ asset('js/cartas.js') }}"></script>
    <script src="{{ asset('js/sala.js') }}"></script>
</body>
</html>
