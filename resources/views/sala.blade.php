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
            <p class="espera__ayuda">Este dispositivo no tiene una sesión guardada para la sala {{ $codigo }}.</p>
            <a href="/" class="boton">Volver al inicio</a>
        </div>

        <div id="vista-espera" class="espera oculto">
            <span class="espera__etiqueta">Compartí este código</span>
            <span class="espera__codigo" id="espera-codigo">{{ $codigo }}</span>
            <div class="pulso"></div>
            <p class="espera__ayuda">Esperando a que se una el otro jugador. Dejá esta pantalla abierta.</p>
        </div>

        <div id="vista-mesa" class="mesa oculto">
            <div class="cab">
                <div class="cab__marca">
                    <span class="marca">Rumi 500</span>
                    <span style="font:600 11px/1 Archivo,sans-serif">Sala {{ $codigo }}</span>
                </div>
                <div class="pill-turno" id="pill-turno">
                    <span class="pill-turno__punto" id="pill-turno-punto"></span>
                    <span class="pill-turno__texto" id="pill-turno-texto"></span>
                </div>
                <div class="marcadores">
                    <div class="marcador">
                        <span class="marcador__etiqueta" style="color:#E8A21C">VOS</span>
                        <span class="marcador__valor" id="marcador-yo">0</span>
                    </div>
                    <div class="marcador">
                        <span class="marcador__etiqueta" style="color:#8B6BE0">RIVAL</span>
                        <span class="marcador__valor" id="marcador-rival">0</span>
                    </div>
                </div>
            </div>

            <div class="franja-rival">
                <span class="panel-titulo">RIVAL</span>
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
                <div class="fila-mano-titulo">
                    <span class="panel-titulo">TU MANO</span>
                    <span id="mano-cantidad" style="font:400 9.5px/1 Archivo,sans-serif;color:rgba(247,246,242,.4)"></span>
                </div>
                <div class="mano-scroll"><div class="mano" id="mano"></div></div>
            </div>
        </div>

        <div id="aviso-reconectar" class="aviso-reconectar oculto">Reconectando…</div>
    </div>

    <script src="{{ asset('js/almacenamiento.js') }}"></script>
    <script src="{{ asset('js/api.js') }}"></script>
    <script src="{{ asset('js/cartas.js') }}"></script>
    <script src="{{ asset('js/sala.js') }}"></script>
</body>
</html>
