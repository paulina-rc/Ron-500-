<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rumi Quinientos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/rumi.css') }}">
</head>
<body>
    <div class="pantalla">
        <div class="contenedor">
            <span class="marca">Rumi Quinientos</span>
            <h1 class="titulo">Jugá una mano</h1>

            <div id="error" class="error oculto"></div>

            <form id="form-crear" class="tarjeta">
                <span class="tarjeta__titulo">Crear una sala nueva</span>
                <div class="campo">
                    <label for="nombre-crear">Tu nombre</label>
                    <input id="nombre-crear" name="nombre" type="text" maxlength="40" required autocomplete="off" placeholder="¿Cómo te llamás?">
                </div>
                <button type="submit" class="boton">Crear sala</button>
            </form>

            <div class="separador">o</div>

            <form id="form-unirse" class="tarjeta">
                <span class="tarjeta__titulo">Unirte con un código</span>
                <div class="campo">
                    <label for="nombre-unirse">Tu nombre</label>
                    <input id="nombre-unirse" name="nombre" type="text" maxlength="40" required autocomplete="off" placeholder="¿Cómo te llamás?">
                </div>
                <div class="campo">
                    <label for="codigo-unirse">Código de la sala</label>
                    <input id="codigo-unirse" name="codigo" type="text" maxlength="4" required autocomplete="off"
                           class="campo--codigo" placeholder="ABCD">
                </div>
                <button type="submit" class="boton boton--fantasma">Unirme</button>
            </form>
        </div>
    </div>

    <script src="{{ asset('js/almacenamiento.js') }}"></script>
    <script src="{{ asset('js/api.js') }}"></script>
    <script src="{{ asset('js/inicio.js') }}"></script>
</body>
</html>
