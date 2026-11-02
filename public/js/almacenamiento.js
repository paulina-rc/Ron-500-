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

    /**
     * El orden en que el jugador acomodó su mano: nunca se manda al servidor.
     * Se guarda junto con el número de ronda, porque en cada ronda se reparten
     * cartas con los mismos ids y un orden viejo desacomodaría la mano nueva.
     */
    function guardarOrdenMano(codigo, idsEnOrden, ronda) {
        localStorage.setItem(claveOrden(codigo), JSON.stringify({ ronda: ronda || 1, ids: idsEnOrden }));
    }

    function obtenerOrdenMano(codigo, ronda) {
        var crudo = localStorage.getItem(claveOrden(codigo));
        if (!crudo) return [];
        try {
            var datos = JSON.parse(crudo);
            if (Array.isArray(datos)) return (ronda || 1) === 1 ? datos : []; // formato viejo, sin ronda
            if (!datos || datos.ronda !== (ronda || 1) || !Array.isArray(datos.ids)) return [];
            return datos.ids;
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
