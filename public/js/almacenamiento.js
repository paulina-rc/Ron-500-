/** Guarda el token del jugador en localStorage, por sala, para poder reconectar. */
(function () {
    'use strict';

    function clave(codigo) {
        return 'rumi:sala:' + codigo.toUpperCase();
    }

    function guardarSesion(codigo, datos) {
        localStorage.setItem(clave(codigo), JSON.stringify(datos));
    }

    function obtenerSesion(codigo) {
        var crudo = localStorage.getItem(clave(codigo));
        if (!crudo) return null;
        try {
            return JSON.parse(crudo);
        } catch (e) {
            return null;
        }
    }

    function borrarSesion(codigo) {
        localStorage.removeItem(clave(codigo));
    }

    window.RumiStorage = {
        guardarSesion: guardarSesion,
        obtenerSesion: obtenerSesion,
        borrarSesion: borrarSesion,
    };
})();
