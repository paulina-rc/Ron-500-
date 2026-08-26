(function () {
    'use strict';

    var TIEMPO_POLL_MS = 1500;
    var ANCHO_MANO = 54;
    var ANCHO_MESA = 46;
    var ANCHO_MAZO = 54;
    var ANCHO_BASURA = 50;

    var app = document.getElementById('app');
    var codigo = app.dataset.codigo;

    var vistaSinAcceso = document.getElementById('vista-sin-acceso');
    var vistaEspera = document.getElementById('vista-espera');
    var vistaMesa = document.getElementById('vista-mesa');
    var avisoReconectar = document.getElementById('aviso-reconectar');

    var elPillPunto = document.getElementById('pill-turno-punto');
    var elPillTexto = document.getElementById('pill-turno-texto');
    var elMarcadorYo = document.getElementById('marcador-yo');
    var elMarcadorRival = document.getElementById('marcador-rival');
    var elDorsosRival = document.getElementById('dorsos-rival');
    var elRivalCantidad = document.getElementById('rival-cantidad');
    var elJugadas = document.getElementById('jugadas');
    var elMesaVacia = document.getElementById('mesa-vacia');
    var elMazo = document.getElementById('mazo');
    var elBasura = document.getElementById('basura');
    var elMensaje = document.getElementById('mensaje');
    var elManoCantidad = document.getElementById('mano-cantidad');
    var elMano = document.getElementById('mano');

    var sesion = RumiStorage.obtenerSesion(codigo);
    if (!sesion || !sesion.token || !sesion.jugadorId) {
        vistaSinAcceso.classList.remove('oculto');
        return;
    }

    var version = 0;
    var temporizador = null;
    var enVuelo = false;

    function mostrar(el) {
        [vistaSinAcceso, vistaEspera, vistaMesa].forEach(function (v) {
            v.classList.toggle('oculto', v !== el);
        });
    }

    function limpiar(el) {
        while (el.firstChild) el.removeChild(el.firstChild);
    }

    function mostrarAvisoReconectar() {
        avisoReconectar.classList.remove('oculto');
    }

    function ocultarAvisoReconectar() {
        avisoReconectar.classList.add('oculto');
    }

    // ---- render: espera ----

    function renderEspera() {
        mostrar(vistaEspera);
    }

    // ---- render: mesa ----

    function renderMesa(cuerpo) {
        mostrar(vistaMesa);

        var miId = sesion.jugadorId;
        var idsJugadores = Object.keys(cuerpo.jugadores);
        var rivalId = idsJugadores.filter(function (id) { return id !== miId; })[0];
        var yo = cuerpo.jugadores[miId];
        var rival = rivalId ? cuerpo.jugadores[rivalId] : null;

        renderTurno(cuerpo, miId);
        renderMarcadores(cuerpo, miId, rivalId);
        renderRival(rival);
        renderMesaJugadas(cuerpo.mesa, miId);
        renderMazo(cuerpo.cartasEnMazo);
        renderBasura(cuerpo.basura);
        renderMensaje(cuerpo, miId);
        renderMano(yo ? yo.mano : [], cuerpo.cartaObligatoriaId);
    }

    function renderTurno(cuerpo, miId) {
        var esMiTurno = cuerpo.turnoActual === miId;
        var color = esMiTurno ? RumiCartas.COLOR_YO : RumiCartas.COLOR_RIVAL;

        elPillPunto.style.background = color;
        elPillTexto.style.color = color;
        document.getElementById('pill-turno').style.background = esMiTurno ? 'rgba(232,162,28,.14)' : 'rgba(139,107,224,.14)';
        document.getElementById('pill-turno').style.border = '1px solid ' + (esMiTurno ? 'rgba(232,162,28,.4)' : 'rgba(139,107,224,.4)');

        if (cuerpo.rondaTerminada) {
            elPillTexto.textContent = 'RONDA TERMINADA';
        } else {
            elPillTexto.textContent = esMiTurno ? 'TU TURNO' : 'TURNO RIVAL';
        }
    }

    function renderMarcadores(cuerpo, miId, rivalId) {
        var puntajes = cuerpo.puntajes || {};
        elMarcadorYo.textContent = puntajes[miId] != null ? puntajes[miId] : 0;
        elMarcadorRival.textContent = rivalId && puntajes[rivalId] != null ? puntajes[rivalId] : 0;
    }

    function renderRival(rival) {
        limpiar(elDorsosRival);
        var cantidad = rival ? rival.cantidadCartas : 0;
        for (var i = 0; i < cantidad; i++) {
            var dorso = document.createElement('div');
            dorso.className = 'dorso-mini';
            elDorsosRival.appendChild(dorso);
        }
        elRivalCantidad.textContent = cantidad + (cantidad === 1 ? ' carta' : ' cartas');
    }

    /**
     * Marca de dueño: SOLO en las cartas cuyo dueño es distinto al del jugador
     * que bajó la jugada. Como la API no manda explícitamente "quién la bajó",
     * se infiere: la jugada nace siempre con un solo dueño en la primera bajada
     * (mínimo 3 cartas) y los embones se agregan de a una; en un trío los embones
     * siempre se agregan al final, así que la primera carta del arreglo es
     * siempre del dueño original. Es una inferencia razonable, no un dato posta
     * del servidor — en una escalera que se extendió por abajo después de un
     * embone ajeno, podría marcar de más; no hay forma de evitarlo sin que el
     * servidor mande el dueño original explícito.
     */
    function duenoDeLaJugada(juego) {
        return juego.cartas.length > 0 ? juego.cartas[0].propietarioId : null;
    }

    function renderMesaJugadas(mesa, miId) {
        limpiar(elJugadas);
        var idsJuegos = Object.keys(mesa || {});
        elMesaVacia.classList.toggle('oculto', idsJuegos.length > 0);

        idsJuegos.forEach(function (juegoId) {
            var juego = mesa[juegoId];
            var creador = duenoDeLaJugada(juego);

            var wrap = document.createElement('div');
            wrap.className = 'jugada';
            var fila = document.createElement('div');
            fila.className = 'jugada__cartas';

            juego.cartas.forEach(function (ce) {
                var esAjena = ce.propietarioId !== creador;
                var dueno = esAjena ? (ce.propietarioId === miId ? 'me' : 'opp') : null;
                fila.appendChild(RumiCartas.crear(ce.carta.numero, ce.carta.palo, { ancho: ANCHO_MESA, dueno: dueno }));
            });

            wrap.appendChild(fila);
            elJugadas.appendChild(wrap);
        });
    }

    function renderMazo(cantidad) {
        limpiar(elMazo);
        elMazo.appendChild(RumiCartas.crearDorso(ANCHO_MAZO, cantidad));
    }

    function renderBasura(basura) {
        limpiar(elBasura);
        basura = basura || [];
        var alto = Math.round(ANCHO_BASURA * 1.42);
        var paso = 20;
        elBasura.style.position = 'relative';
        elBasura.style.width = ANCHO_BASURA + 'px';
        elBasura.style.height = (alto + paso * Math.max(0, basura.length - 1)) + 'px';

        basura.forEach(function (carta, i) {
            var el = RumiCartas.crear(carta.numero, carta.palo, { ancho: ANCHO_BASURA });
            el.style.position = 'absolute';
            el.style.left = '0';
            el.style.top = (i * paso) + 'px';
            el.style.zIndex = String(i + 1);
            elBasura.appendChild(el);
        });
    }

    function renderMensaje(cuerpo, miId) {
        elMensaje.classList.remove('mensaje--obligatoria');

        if (cuerpo.rondaTerminada) {
            if (cuerpo.ganadorRonda === miId) {
                elMensaje.textContent = '¡Ganaste la ronda!';
            } else if (cuerpo.ganadorRonda) {
                elMensaje.textContent = 'Tu rival ganó la ronda.';
            } else {
                elMensaje.textContent = 'Nadie podía comer: la ronda terminó sin gane.';
            }
            return;
        }

        if (cuerpo.cartaObligatoriaId && cuerpo.turnoActual === miId) {
            elMensaje.textContent = 'Tenés que jugar la carta que comiste de la basura antes de botar.';
            elMensaje.classList.add('mensaje--obligatoria');
            return;
        }

        elMensaje.textContent = cuerpo.turnoActual === miId ? 'Es tu turno.' : 'Turno de tu rival…';
    }

    function renderMano(mano, cartaObligatoriaId) {
        limpiar(elMano);
        mano = mano || [];
        elManoCantidad.textContent = mano.length + (mano.length === 1 ? ' carta' : ' cartas');

        mano.forEach(function (carta) {
            var destacada = carta.id === cartaObligatoriaId;
            elMano.appendChild(RumiCartas.crear(carta.numero, carta.palo, { ancho: ANCHO_MANO, destacada: destacada }));
        });
    }

    // ---- polling ----

    function aplicarEstado(cuerpo) {
        if (cuerpo.salaEstado === 'esperando') {
            renderEspera();
        } else {
            renderMesa(cuerpo);
        }
    }

    function irAlInicio() {
        RumiStorage.borrarSesion(codigo);
        window.location.href = '/';
    }

    function programarSiguiente(demora) {
        if (temporizador) clearTimeout(temporizador);
        temporizador = setTimeout(ciclo, demora === undefined ? TIEMPO_POLL_MS : demora);
    }

    function ciclo() {
        if (document.hidden) return;
        if (enVuelo) { programarSiguiente(); return; }

        enVuelo = true;
        RumiApi.obtenerEstado(codigo, sesion.token, version)
            .then(function (r) {
                if (r.status === 204) {
                    ocultarAvisoReconectar();
                    return;
                }
                if (r.status === 401 || r.status === 404) {
                    irAlInicio();
                    return;
                }
                if (r.ok && r.cuerpo) {
                    ocultarAvisoReconectar();
                    version = r.cuerpo.version;
                    aplicarEstado(r.cuerpo);
                    return;
                }
                mostrarAvisoReconectar();
            })
            .catch(function () {
                mostrarAvisoReconectar();
            })
            .then(function () {
                enVuelo = false;
                programarSiguiente();
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            ciclo();
        }
    });

    ciclo();
})();
