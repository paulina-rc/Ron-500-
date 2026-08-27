/** Envoltorio delgado sobre fetch para los endpoints de /api/salas. No decide nada, solo pide y devuelve. */
(function () {
    'use strict';

    async function peticionJson(url, opciones) {
        var resp = await fetch(url, opciones);
        var cuerpo = null;
        if (resp.status !== 204) {
            try {
                cuerpo = await resp.json();
            } catch (e) {
                cuerpo = null;
            }
        }
        return { status: resp.status, ok: resp.ok, cuerpo: cuerpo };
    }

    async function crearSala(nombre) {
        var r = await peticionJson('/api/salas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ nombre: nombre }),
        });
        if (!r.ok) throw new Error((r.cuerpo && r.cuerpo.message) || 'No se pudo crear la sala.');
        return r.cuerpo;
    }

    async function unirseSala(codigo, nombre) {
        var r = await peticionJson('/api/salas/' + encodeURIComponent(codigo) + '/unirse', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ nombre: nombre }),
        });
        if (!r.ok) throw new Error((r.cuerpo && r.cuerpo.message) || 'No se pudo unir a la sala.');
        return r.cuerpo;
    }

    /** Devuelve {status, ok, cuerpo}: quien llama decide qué hacer con 204/401/200. */
    async function obtenerEstado(codigo, token, version) {
        return peticionJson('/api/salas/' + encodeURIComponent(codigo) + '/estado?version=' + encodeURIComponent(version), {
            method: 'GET',
            headers: { 'X-Jugador-Token': token, Accept: 'application/json' },
        });
    }

    /**
     * Ejecuta una acción de juego (comer_mazo, comer_basura, bajar, embonar, botar).
     * Devuelve {status, ok, cuerpo} tal cual: un 422 con el mensaje del motor no es
     * un error de red, es una respuesta legítima que quien llama debe mostrar.
     */
    async function ejecutarAccion(codigo, token, payload) {
        return peticionJson('/api/salas/' + encodeURIComponent(codigo) + '/accion', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Jugador-Token': token, Accept: 'application/json' },
            body: JSON.stringify(payload),
        });
    }

    window.RumiApi = {
        crearSala: crearSala,
        unirseSala: unirseSala,
        obtenerEstado: obtenerEstado,
        ejecutarAccion: ejecutarAccion,
    };
})();
