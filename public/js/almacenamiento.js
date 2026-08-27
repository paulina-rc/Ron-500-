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

    function claveOrden(codigo) {
        return 'rumi:orden-mano:' + codigo.toUpperCase();
    }

    /** El orden en que el jugador acomodó su mano: nunca se manda al servidor. */
    function guardarOrdenMano(codigo, idsEnOrden) {
        localStorage.setItem(claveOrden(codigo), JSON.stringify(idsEnOrden));
    }

    function obtenerOrdenMano(codigo) {
        var crudo = localStorage.getItem(claveOrden(codigo));
        if (!crudo) return [];
        try {
            var datos = JSON.parse(crudo);
            return Array.isArray(datos) ? datos : [];
        } catch (e) {
            return [];
        }
    }

    window.RumiStorage = {
        guardarSesion: guardarSesion,
        obtenerSesion: obtenerSesion,
        borrarSesion: borrarSesion,
        guardarOrdenMano: guardarOrdenMano,
        obtenerOrdenMano: obtenerOrdenMano,
    };
})();
