(function () {
    'use strict';

    var elError = document.getElementById('error');
    var formCrear = document.getElementById('form-crear');
    var formUnirse = document.getElementById('form-unirse');

    // Invitación: /?unirse=ABCD deja el código listo y pide solo el nombre.
    var invitacion = new URLSearchParams(window.location.search).get('unirse');
    if (invitacion && /^[A-Za-z]{4}$/.test(invitacion)) {
        document.getElementById('codigo-unirse').value = invitacion.toUpperCase();
        var separador = document.querySelector('.separador');
        formUnirse.parentNode.insertBefore(formUnirse, formCrear);
        formUnirse.parentNode.insertBefore(separador, formCrear);
        var nombreUnirse = document.getElementById('nombre-unirse');
        nombreUnirse.focus();
    }

    function mostrarError(mensaje) {
        elError.textContent = mensaje;
        elError.classList.remove('oculto');
    }

    function ocultarError() {
        elError.classList.add('oculto');
    }

    function deshabilitar(form, deshabilitado) {
        var boton = form.querySelector('button');
        boton.disabled = deshabilitado;
    }

    formCrear.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        ocultarError();
        var nombre = document.getElementById('nombre-crear').value.trim();
        if (!nombre) return;

        deshabilitar(formCrear, true);
        try {
            var datos = await RumiApi.crearSala(nombre);
            RumiStorage.guardarSesion(datos.codigo, { token: datos.token, jugadorId: datos.jugadorId });
            window.location.href = '/salas/' + datos.codigo;
        } catch (e) {
            mostrarError(e.message);
            deshabilitar(formCrear, false);
        }
    });

    formUnirse.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        ocultarError();
        var nombre = document.getElementById('nombre-unirse').value.trim();
        var codigo = document.getElementById('codigo-unirse').value.trim().toUpperCase();
        if (!nombre || codigo.length !== 4) {
            mostrarError('Completá tu nombre y el código de 4 letras de la sala.');
            return;
        }

        deshabilitar(formUnirse, true);
        try {
            var datos = await RumiApi.unirseSala(codigo, nombre);
            RumiStorage.guardarSesion(codigo, { token: datos.token, jugadorId: datos.jugadorId });
            window.location.href = '/salas/' + codigo;
        } catch (e) {
            mostrarError(e.message);
            deshabilitar(formUnirse, false);
        }
    });
})();
