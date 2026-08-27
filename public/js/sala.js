(function () {
    'use strict';

    var TIEMPO_POLL_MS = 1500;
    var TIEMPO_ERROR_MS = 4500;
    var ANCHO_MANO = 54;
    var ANCHO_MESA = 46;
    var ANCHO_MAZO = 54;
    var ANCHO_BASURA = 50;
    var ANCHO_HOJA = 46;

    var app = document.getElementById('app');
    var codigo = app.dataset.codigo;

    var vistaSinAcceso = document.getElementById('vista-sin-acceso');
    var vistaEspera = document.getElementById('vista-espera');
    var vistaMesa = document.getElementById('vista-mesa');
    var avisoReconectar = document.getElementById('aviso-reconectar');

    var elPill = document.getElementById('pill-turno');
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
    var elManoScroll = document.querySelector('.mano-scroll');

    var btnBajar = document.getElementById('btn-bajar');
    var btnBotar = document.getElementById('btn-botar');
    var btnMoverIzq = document.getElementById('btn-mover-izq');
    var btnMoverDer = document.getElementById('btn-mover-der');

    var hojaFondo = document.getElementById('hoja-fondo');
    var hojaComer = document.getElementById('hoja-comer');
    var hojaComerDetalle = document.getElementById('hoja-comer-detalle');
    var hojaComerCancelar = document.getElementById('hoja-comer-cancelar');
    var hojaComerConfirmar = document.getElementById('hoja-comer-confirmar');

    var sesion = RumiStorage.obtenerSesion(codigo);
    if (!sesion || !sesion.token || !sesion.jugadorId) {
        vistaSinAcceso.classList.remove('oculto');
        return;
    }

    // ---- estado local ----

    var version = 0;
    var estadoActual = null;
    var seleccion = [];
    var ordenManoLocal = RumiStorage.obtenerOrdenMano(codigo);
    var accionEnCurso = false;
    var errorActivo = null; // { mensaje, hasta }
    var objetivoHojaBasura = null; // id de la carta de basura que se está por comer

    var temporizador = null;
    var enVuelo = false;

    function mostrarVista(el) {
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

    function mostrarErrorTemporal(mensaje) {
        errorActivo = { mensaje: mensaje, hasta: Date.now() + TIEMPO_ERROR_MS };
        render();
        setTimeout(function () {
            if (errorActivo && Date.now() >= errorActivo.hasta) {
                errorActivo = null;
                render();
            }
        }, TIEMPO_ERROR_MS + 50);
    }

    // ---- selección y orden de la mano (todo local, nunca se manda al servidor) ----

    function estaSeleccionada(id) {
        return seleccion.indexOf(id) >= 0;
    }

    function alternarSeleccion(id) {
        var i = seleccion.indexOf(id);
        if (i >= 0) seleccion.splice(i, 1);
        else seleccion.push(id);
        render();
    }

    function reconciliarOrden(manoServidor) {
        var idsServidor = manoServidor.map(function (c) { return c.id; });
        var nuevoOrden = ordenManoLocal.filter(function (id) { return idsServidor.indexOf(id) >= 0; });
        idsServidor.forEach(function (id) {
            if (nuevoOrden.indexOf(id) < 0) nuevoOrden.push(id);
        });
        ordenManoLocal = nuevoOrden;
        RumiStorage.guardarOrdenMano(codigo, ordenManoLocal);

        var porId = {};
        manoServidor.forEach(function (c) { porId[c.id] = c; });
        return ordenManoLocal.map(function (id) { return porId[id]; });
    }

    function moverSeleccionada(direccion) {
        if (seleccion.length !== 1 || accionEnCurso) return;
        var id = seleccion[0];
        var i = ordenManoLocal.indexOf(id);
        var j = i + direccion;
        if (i < 0 || j < 0 || j >= ordenManoLocal.length) return;

        var copia = ordenManoLocal.slice();
        copia.splice(i, 1);
        copia.splice(j, 0, id);
        ordenManoLocal = copia;
        RumiStorage.guardarOrdenMano(codigo, ordenManoLocal);
        render();
    }

    // ---- acciones contra el servidor ----

    function irAlInicio() {
        RumiStorage.borrarSesion(codigo);
        window.location.href = '/';
    }

    async function ejecutar(payload) {
        if (accionEnCurso) return;
        accionEnCurso = true;
        errorActivo = null;
        render();

        try {
            var r = await RumiApi.ejecutarAccion(codigo, sesion.token, payload);

            if (r.status === 401) {
                irAlInicio();
                return;
            }
            if (!r.ok) {
                mostrarErrorTemporal((r.cuerpo && r.cuerpo.message) || 'No se pudo hacer esa jugada.');
                return;
            }

            // éxito: refrescamos con la respuesta de la acción, sin esperar el próximo polling
            seleccion = [];
            version = r.cuerpo.version;
            estadoActual = r.cuerpo;
        } catch (e) {
            mostrarErrorTemporal('No se pudo conectar con el servidor. Reintentá.');
        } finally {
            accionEnCurso = false;
            render();
        }
    }

    function comerDelMazo() {
        ejecutar({ accion: 'comer_mazo' });
    }

    function abrirHojaBasura(cartaId) {
        objetivoHojaBasura = cartaId;
        render();
    }

    function cerrarHojaBasura() {
        objetivoHojaBasura = null;
        render();
    }

    function confirmarComerBasura() {
        var cartaId = objetivoHojaBasura;
        objetivoHojaBasura = null;
        if (cartaId) ejecutar({ accion: 'comer_basura', cartaId: cartaId });
    }

    function bajarSeleccion() {
        if (seleccion.length < 1) return;
        ejecutar({ accion: 'bajar', cartaIds: seleccion.slice() });
    }

    function botarSeleccion() {
        if (seleccion.length !== 1) return;
        ejecutar({ accion: 'botar', cartaId: seleccion[0] });
    }

    function embonarEn(juegoId) {
        if (seleccion.length !== 1) return;
        ejecutar({ accion: 'embonar', juegoId: juegoId, cartaId: seleccion[0] });
    }

    btnBajar.addEventListener('click', bajarSeleccion);
    btnBotar.addEventListener('click', botarSeleccion);
    btnMoverIzq.addEventListener('click', function () { moverSeleccionada(-1); });
    btnMoverDer.addEventListener('click', function () { moverSeleccionada(1); });
    hojaComerCancelar.addEventListener('click', cerrarHojaBasura);
    hojaFondo.addEventListener('click', cerrarHojaBasura);
    hojaComerConfirmar.addEventListener('click', confirmarComerBasura);

    // ---- render ----

    function render() {
        if (!estadoActual) return;

        if (estadoActual.salaEstado === 'esperando') {
            mostrarVista(vistaEspera);
            return;
        }

        mostrarVista(vistaMesa);

        var miId = sesion.jugadorId;
        var e = estadoActual;
        var esMiTurno = e.turnoActual === miId;
        var debeComer = e.fase === 'debe_comer';
        var puedeJugar = e.fase === 'puede_jugar';
        var puedeComerAhora = esMiTurno && debeComer && !accionEnCurso && !e.rondaTerminada;
        var puedeJugarCartasAhora = esMiTurno && puedeJugar && !accionEnCurso && !e.rondaTerminada;

        var idsJugadores = Object.keys(e.jugadores);
        var rivalId = idsJugadores.filter(function (id) { return id !== miId; })[0];
        var yo = e.jugadores[miId];
        var rival = rivalId ? e.jugadores[rivalId] : null;

        renderTurno(e, esMiTurno);
        renderMarcadores(e, miId, rivalId);
        renderRival(rival);
        renderMazo(e.cartasEnMazo, puedeComerAhora);
        renderBasura(e.basura, puedeComerAhora);
        renderMesaJugadas(e.mesa, miId, puedeJugarCartasAhora);
        renderMensaje(e, miId, esMiTurno);
        renderMano(yo ? yo.mano : [], e.cartaObligatoriaId, puedeJugarCartasAhora);
        renderBotones(puedeJugarCartasAhora, e.cartaObligatoriaId);
        renderHojaBasura(e);
    }

    function renderTurno(e, esMiTurno) {
        var color = esMiTurno ? RumiCartas.COLOR_YO : RumiCartas.COLOR_RIVAL;
        elPillPunto.style.background = color;
        elPillTexto.style.color = color;
        elPill.style.background = esMiTurno ? 'rgba(232,162,28,.14)' : 'rgba(139,107,224,.14)';
        elPill.style.border = '1px solid ' + (esMiTurno ? 'rgba(232,162,28,.4)' : 'rgba(139,107,224,.4)');

        elPillTexto.textContent = e.rondaTerminada ? 'RONDA TERMINADA' : (esMiTurno ? 'TU TURNO' : 'TURNO RIVAL');
    }

    function renderMarcadores(e, miId, rivalId) {
        var puntajes = e.puntajes || {};
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

    function renderMazo(cantidad, activo) {
        limpiar(elMazo);
        elMazo.appendChild(RumiCartas.crearDorso(ANCHO_MAZO, cantidad, {
            atenuado: !activo,
            onTap: activo ? comerDelMazo : null,
        }));
    }

    function renderBasura(basura, activo) {
        limpiar(elBasura);
        basura = basura || [];
        var alto = Math.round(ANCHO_BASURA * 1.42);
        var paso = 20;
        elBasura.style.position = 'relative';
        elBasura.style.width = ANCHO_BASURA + 'px';
        elBasura.style.height = (alto + paso * Math.max(0, basura.length - 1)) + 'px';

        basura.forEach(function (carta, i) {
            var el = RumiCartas.crear(carta.numero, carta.palo, {
                ancho: ANCHO_BASURA,
                atenuada: !activo,
                onTap: activo ? function () { abrirHojaBasura(carta.id); } : null,
            });
            el.style.position = 'absolute';
            el.style.left = '0';
            el.style.top = (i * paso) + 'px';
            el.style.zIndex = String(i + 1);
            elBasura.appendChild(el);
        });
    }

    function renderMesaJugadas(mesa, miId, activo) {
        limpiar(elJugadas);
        var idsJuegos = Object.keys(mesa || {});
        elMesaVacia.classList.toggle('oculto', idsJuegos.length > 0);

        var puedeEmbonar = activo && seleccion.length === 1;

        idsJuegos.forEach(function (juegoId) {
            var juego = mesa[juegoId];
            var creador = juego.creadorId;

            var wrap = document.createElement('div');
            wrap.className = 'jugada' + (puedeEmbonar ? ' jugada--tocable' : '');
            if (puedeEmbonar) {
                wrap.addEventListener('click', function () { embonarEn(juegoId); });
            }

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

    function renderMensaje(e, miId, esMiTurno) {
        elMensaje.classList.remove('mensaje--obligatoria', 'mensaje--error');

        if (errorActivo && Date.now() < errorActivo.hasta) {
            elMensaje.textContent = errorActivo.mensaje;
            elMensaje.classList.add('mensaje--error');
            return;
        }

        if (e.rondaTerminada) {
            if (e.ganadorRonda === miId) {
                elMensaje.textContent = '¡Ganaste la ronda!';
            } else if (e.ganadorRonda) {
                elMensaje.textContent = 'Tu rival ganó la ronda.';
            } else {
                elMensaje.textContent = 'Nadie podía comer: la ronda terminó sin gane.';
            }
            return;
        }

        if (e.cartaObligatoriaId && esMiTurno) {
            elMensaje.textContent = 'Tenés que jugar la carta que comiste de la basura antes de botar.';
            elMensaje.classList.add('mensaje--obligatoria');
            return;
        }

        if (!esMiTurno) {
            elMensaje.textContent = 'Turno de tu rival…';
        } else if (e.fase === 'debe_comer') {
            elMensaje.textContent = 'Tocá el mazo o una carta de la basura para comer.';
        } else if (seleccion.length === 1) {
            elMensaje.textContent = 'Tocá "Botar" para descartarla, o una jugada de la mesa para embonarla.';
        } else if (seleccion.length >= 2) {
            elMensaje.textContent = 'Tocá "Bajar" para poner esa jugada en la mesa.';
        } else {
            elMensaje.textContent = 'Seleccioná cartas de tu mano para bajar, embonar o botar.';
        }
    }

    function renderMano(mano, cartaObligatoriaId, activo) {
        limpiar(elMano);
        mano = mano || [];
        var manoOrdenada = reconciliarOrden(mano);

        elManoCantidad.textContent = mano.length + (mano.length === 1 ? ' carta' : ' cartas');
        elManoScroll.classList.toggle('mano--inactiva', accionEnCurso);

        manoOrdenada.forEach(function (carta) {
            var destacada = carta.id === cartaObligatoriaId;
            var seleccionada = estaSeleccionada(carta.id);
            elMano.appendChild(RumiCartas.crear(carta.numero, carta.palo, {
                ancho: ANCHO_MANO,
                destacada: destacada,
                seleccionada: seleccionada,
                onTap: accionEnCurso ? null : function () { alternarSeleccion(carta.id); },
            }));
        });

        // limpiamos de la selección cualquier id que ya no esté en la mano (se jugó/descartó)
        var idsActuales = mano.map(function (c) { return c.id; });
        seleccion = seleccion.filter(function (id) { return idsActuales.indexOf(id) >= 0; });
    }

    function renderBotones(puedeJugarCartasAhora, cartaObligatoriaId) {
        btnBajar.disabled = !puedeJugarCartasAhora || seleccion.length < 1;
        btnBotar.disabled = !puedeJugarCartasAhora || seleccion.length !== 1 || !!cartaObligatoriaId;
        btnMoverIzq.disabled = accionEnCurso || seleccion.length !== 1 || ordenManoLocal.indexOf(seleccion[0]) <= 0;
        btnMoverDer.disabled = accionEnCurso || seleccion.length !== 1
            || ordenManoLocal.indexOf(seleccion[0]) < 0
            || ordenManoLocal.indexOf(seleccion[0]) >= ordenManoLocal.length - 1;
    }

    function renderHojaBasura(e) {
        if (!objetivoHojaBasura) {
            hojaFondo.classList.add('oculto');
            hojaComer.classList.add('oculto');
            return;
        }

        var basura = e.basura || [];
        var indice = -1;
        basura.forEach(function (c, i) { if (c.id === objetivoHojaBasura) indice = i; });
        if (indice < 0) {
            objetivoHojaBasura = null;
            hojaFondo.classList.add('oculto');
            hojaComer.classList.add('oculto');
            return;
        }

        var objetivo = basura[indice];
        var cartasEncima = basura.slice(indice + 1);

        limpiar(hojaComerDetalle);

        var texto = document.createElement('div');
        if (cartasEncima.length === 0) {
            texto.textContent = 'Vas a comer esta carta. Queda obligada a salir a la mesa este turno (bajada nueva o embone).';
        } else {
            texto.textContent = 'Vas a comer ' + (cartasEncima.length + 1) + ' cartas: la de abajo, marcada, '
                + 'queda obligada a salir a la mesa este turno. Las demás quedan libres en tu mano.';
        }
        hojaComerDetalle.appendChild(texto);

        var fila = document.createElement('div');
        fila.className = 'hoja__cartas';
        fila.appendChild(RumiCartas.crear(objetivo.numero, objetivo.palo, { ancho: ANCHO_HOJA, destacada: true }));
        cartasEncima.forEach(function (c) {
            fila.appendChild(RumiCartas.crear(c.numero, c.palo, { ancho: ANCHO_HOJA }));
        });
        hojaComerDetalle.appendChild(fila);

        hojaFondo.classList.remove('oculto');
        hojaComer.classList.remove('oculto');
    }

    // ---- polling ----

    function aplicarEstado(cuerpo) {
        estadoActual = cuerpo;
        render();
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
