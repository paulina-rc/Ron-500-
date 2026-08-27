/**
 * Motor de dibujo de cartas. Puerto directo de la lógica del diseño de referencia
 * (Rumi Quinientos.dc.html): tamaños y tipografías siempre como fracción del ancho
 * que se le pida a cada carta, para que luzcan igual en la mano, la mesa, el mazo
 * y la basura aunque cada una use un tamaño distinto.
 */
(function () {
    'use strict';

    var PALOS = {
        H: { glifo: '♥', color: '#C8283C', nombre: 'Corazones' },
        D: { glifo: '♦', color: '#1F5FA8', nombre: 'Diamantes' },
        C: { glifo: '♣', color: '#12795A', nombre: 'Tréboles' },
        S: { glifo: '♠', color: '#1A1C20', nombre: 'Picas' },
    };
    var FUENTE = "Archivo, system-ui, sans-serif";
    var COLOR_YO = '#E8A21C';
    var COLOR_RIVAL = '#8B6BE0';
    var ASPECTO = 1.42;

    function r(n) {
        return Math.round(n);
    }

    function pipLayout(numero) {
        var L = 29, R = 71, M = 50, f = true;
        var cols3 = [[L, 15], [R, 15], [L, 50], [R, 50], [L, 85, f], [R, 85, f]];
        var cols4 = [[L, 15], [R, 15], [L, 38], [R, 38], [L, 62, f], [R, 62, f], [L, 85, f], [R, 85, f]];
        var mapa = {
            A: [[M, 50]],
            2: [[M, 15], [M, 85, f]],
            3: [[M, 15], [M, 50], [M, 85, f]],
            4: [[L, 15], [R, 15], [L, 85, f], [R, 85, f]],
            5: [[L, 15], [R, 15], [M, 50], [L, 85, f], [R, 85, f]],
            6: cols3,
            7: cols3.concat([[M, 32]]),
            8: cols3.concat([[M, 32], [M, 68, f]]),
            9: cols4.concat([[M, 50]]),
            10: cols4.concat([[M, 26], [M, 74, f]]),
        };
        return mapa[numero] || null;
    }

    function crearCentro(numero, palo, ancho) {
        var esFigura = ['J', 'Q', 'K'].indexOf(numero) >= 0;
        var color = PALOS[palo].color;
        var glifo = PALOS[palo].glifo;
        var centro = document.createElement('div');
        centro.style.cssText = 'position:absolute;inset:0';

        var espejo = document.createElement('span');
        espejo.textContent = glifo;
        espejo.style.cssText = 'position:absolute;right:' + r(ancho * 0.09) + 'px;bottom:' + r(ancho * 0.07) + 'px;'
            + 'transform:rotate(180deg);font:400 ' + r(ancho * 0.20) + 'px/1 ' + FUENTE + ';color:' + color + ';opacity:.85';
        centro.appendChild(espejo);

        if (esFigura) {
            var panel = document.createElement('div');
            panel.textContent = numero;
            panel.style.cssText = 'position:absolute;left:' + r(ancho * 0.20) + 'px;right:' + r(ancho * 0.20) + 'px;'
                + 'top:' + r(ancho * 0.30) + 'px;bottom:' + r(ancho * 0.30) + 'px;border:1px solid ' + color + ';'
                + 'border-radius:' + r(ancho * 0.04) + 'px;display:flex;align-items:center;justify-content:center;'
                + 'color:' + color + ';font:600 ' + r(ancho * 0.34) + 'px/1 ' + FUENTE;
            centro.appendChild(panel);

            var arriba = document.createElement('span');
            arriba.textContent = glifo;
            arriba.style.cssText = 'position:absolute;left:50%;top:' + r(ancho * 0.16) + 'px;transform:translateX(-50%);'
                + 'font:400 ' + r(ancho * 0.19) + 'px/1 ' + FUENTE + ';color:' + color;
            centro.appendChild(arriba);

            var abajo = document.createElement('span');
            abajo.textContent = glifo;
            abajo.style.cssText = 'position:absolute;left:50%;bottom:' + r(ancho * 0.16) + 'px;'
                + 'transform:translateX(-50%) rotate(180deg);font:400 ' + r(ancho * 0.19) + 'px/1 ' + FUENTE + ';color:' + color;
            centro.appendChild(abajo);

            return centro;
        }

        var disposicion = pipLayout(numero) || [];
        var esAs = numero === 'A';
        var caja = document.createElement('div');
        caja.style.cssText = 'position:absolute;left:' + r(ancho * 0.14) + 'px;right:' + r(ancho * 0.14) + 'px;'
            + 'top:' + r(ancho * 0.30) + 'px;bottom:' + r(ancho * 0.16) + 'px';
        disposicion.forEach(function (p) {
            var pip = document.createElement('span');
            pip.textContent = glifo;
            var rota = p[2] ? ' rotate(180deg)' : '';
            pip.style.cssText = 'position:absolute;left:' + p[0] + '%;top:' + p[1] + '%;'
                + 'transform:translate(-50%,-50%)' + rota + ';'
                + 'font:400 ' + r(ancho * (esAs ? 0.42 : 0.20)) + 'px/1 ' + FUENTE + ';color:' + color;
            caja.appendChild(pip);
        });
        centro.appendChild(caja);
        return centro;
    }

    /**
     * @param {string} numero  'A'..'K','10'
     * @param {string} palo    'H'|'D'|'C'|'S'
     * @param {object} [opciones]
     * @param {number} [opciones.ancho=60]
     * @param {'me'|'opp'|null} [opciones.dueno]  marca de color; null = sin marca (carta propia del juego, no embonada)
     * @param {boolean} [opciones.destacada]      resalta el borde (carta obligatoria)
     * @param {boolean} [opciones.seleccionada]   la levanta y le da un halo claro (selección del jugador)
     * @param {boolean} [opciones.atenuada]       la apaga visualmente (no es su turno / no corresponde todavía)
     * @param {function} [opciones.onTap]         si se pasa, la carta queda tocable
     */
    function crear(numero, palo, opciones) {
        opciones = opciones || {};
        var ancho = opciones.ancho || 60;
        var alto = r(ancho * ASPECTO);
        var radio = Math.max(4, r(ancho * 0.105));
        var color = PALOS[palo].color;
        var esDiezOMas = numero === '10';
        var dueno = opciones.dueno || null;
        var barraAncho = dueno ? Math.max(4, r(ancho * 0.08)) : 0;
        var elevada = opciones.seleccionada ? ' translateY(-10px)' : '';

        var carta = document.createElement('div');
        carta.className = 'carta';
        carta.style.cssText = 'position:relative;width:' + ancho + 'px;height:' + alto + 'px;box-sizing:border-box;'
            + 'border-radius:' + radio + 'px;background:#F7F6F2;overflow:hidden;flex:none;'
            + 'transform:' + (elevada || 'none') + ';transition:transform .12s ease,opacity .12s ease;'
            + 'opacity:' + (opciones.atenuada ? '.45' : '1') + ';'
            + (opciones.onTap ? 'cursor:pointer;-webkit-tap-highlight-color:transparent;' : '')
            + 'border:' + (opciones.seleccionada ? '2px solid #F7F6F2' : (opciones.destacada ? '2px solid ' + COLOR_YO : '1px solid rgba(16,28,32,.20)')) + ';'
            + 'box-shadow:' + (
                opciones.seleccionada
                    ? '0 8px 16px rgba(16,51,59,.45)' + (opciones.destacada ? ',0 0 0 3px rgba(232,162,28,.28)' : '')
                    : (opciones.destacada ? '0 0 0 3px rgba(232,162,28,.28)' : '0 1px 2px rgba(16,51,59,.22)')
            );

        if (opciones.onTap) {
            carta.addEventListener('click', opciones.onTap);
        }

        if (dueno) {
            var barra = document.createElement('div');
            var relleno = dueno === 'opp'
                ? 'repeating-linear-gradient(180deg,' + COLOR_RIVAL + ' 0 5px,#F7F6F2 5px 8px)'
                : COLOR_YO;
            barra.style.cssText = 'position:absolute;left:0;top:0;bottom:0;width:' + barraAncho + 'px;background:' + relleno;
            carta.appendChild(barra);
        }

        var esquina = document.createElement('div');
        var espacioBarra = dueno ? Math.max(4, r(ancho * 0.08)) : 0;
        esquina.style.cssText = 'position:absolute;top:' + r(ancho * 0.05) + 'px;'
            + 'left:' + (r(ancho * (esDiezOMas ? 0.055 : 0.085)) + espacioBarra) + 'px;'
            + 'display:flex;align-items:baseline;gap:' + (esDiezOMas ? 0 : Math.max(1, r(ancho * 0.032))) + 'px;line-height:1';

        var rango = document.createElement('span');
        rango.textContent = numero;
        rango.style.cssText = 'font:700 ' + r(ancho * (esDiezOMas ? 0.265 : 0.31)) + 'px/0.84 ' + FUENTE + ';'
            + 'letter-spacing:' + (esDiezOMas ? '-0.09em' : '-0.035em') + ';color:' + color;
        esquina.appendChild(rango);

        var pipEsquina = document.createElement('span');
        pipEsquina.textContent = PALOS[palo].glifo;
        pipEsquina.style.cssText = 'font:400 ' + r(ancho * (esDiezOMas ? 0.20 : 0.235)) + 'px/0.9 ' + FUENTE + ';color:' + color;
        esquina.appendChild(pipEsquina);

        carta.appendChild(esquina);
        carta.appendChild(crearCentro(numero, palo, ancho));

        return carta;
    }

    /**
     * El dorso del mazo: un cuadro con la cuenta de cartas restantes.
     * @param {object} [opciones]
     * @param {boolean} [opciones.atenuado]
     * @param {function} [opciones.onTap]
     */
    function crearDorso(ancho, cantidad, opciones) {
        opciones = opciones || {};
        var alto = r(ancho * ASPECTO);
        var relleno = Math.max(3, r(ancho * 0.07));

        var caja = document.createElement('div');
        caja.style.cssText = 'width:' + ancho + 'px;height:' + alto + 'px;border-radius:' + r(ancho * 0.105) + 'px;'
            + 'box-sizing:border-box;padding:' + relleno + 'px;background:#F7F6F2;opacity:' + (opciones.atenuado ? '.45' : '1') + ';'
            + (opciones.onTap ? 'cursor:pointer;-webkit-tap-highlight-color:transparent;' : '')
            + 'border:1px solid rgba(16,28,32,.20);display:flex;align-items:center;justify-content:center;flex:none;'
            + 'box-shadow:inset 0 0 0 ' + relleno + 'px #F7F6F2,inset 0 0 0 ' + (relleno + 1) + 'px rgba(31,95,168,.55)';

        if (opciones.onTap) {
            caja.addEventListener('click', opciones.onTap);
        }

        var interior = document.createElement('div');
        interior.textContent = cantidad;
        interior.style.cssText = 'width:100%;height:100%;border-radius:' + r(ancho * 0.05) + 'px;'
            + 'background:repeating-linear-gradient(45deg,transparent 0 3px,rgba(247,246,242,.5) 3px 4px),'
            + 'repeating-linear-gradient(-45deg,transparent 0 3px,rgba(247,246,242,.5) 3px 4px),#1F5FA8;'
            + 'display:flex;align-items:center;justify-content:center;'
            + 'font:600 ' + r(ancho * 0.2) + 'px/1 ' + FUENTE + ';letter-spacing:.06em;color:rgba(247,246,242,.9)';
        caja.appendChild(interior);

        return caja;
    }

    window.RumiCartas = {
        PALOS: PALOS,
        COLOR_YO: COLOR_YO,
        COLOR_RIVAL: COLOR_RIVAL,
        crear: crear,
        crearDorso: crearDorso,
    };
})();
