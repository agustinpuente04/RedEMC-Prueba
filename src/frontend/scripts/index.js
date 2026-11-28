$(function () {
    'use strict';

    // Configuración y estado
    const API_PERSONAS = '/api/personas';
    const DURACION_ALERTA = 5000;
    const REGEX_NOMBRE = /^[\p{L} ]+$/u;
    const REGEX_EMAIL = /^(?!.*\.\.)[A-Za-z0-9](?:[A-Za-z0-9._%+-]{0,62}[A-Za-z0-9])?@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/;
    const REGEX_TELEFONO = /^09[0-9]{7}$/;

    const estado = {
        pagina: 1,
        totalPaginas: 1,
        enviando: false,
        timerPagina: null
    };

    const $formulario = $('#formulario-persona');
    const $alertaFormulario = $('#alerta-formulario');
    const $alertaListado = $('#alerta-listado');
    const $botonGuardar = $('#boton-guardar');
    const $textoBoton = $('#texto-boton');
    const $cargandoBoton = $('#cargando-boton');
    const $listado = $('#contenedor-listado');
    const $paginacion = $('#contenedor-paginacion');
    const $contador = $('#contador-registros');

    const campos = {
        nombre: { $input: $('#nombre'), $error: $('#error-nombre') },
        email: { $input: $('#email'), $error: $('#error-email') },
        telefono: { $input: $('#telefono'), $error: $('#error-telefono') }
    };

    // Eventos
    $formulario.on('submit', manejarEnvio);

    for (const campo of Object.values(campos)) {
        campo.$input.on('input', function () {
            campo.$input.removeClass('es-invalido');
            campo.$error.text('');
            ocultarAlerta($alertaFormulario);
        });
    }

    $('.alerta-estado').on('click', '.alerta-cerrar', function () {
        ocultarAlerta($(this).closest('.alerta-estado'));
    });

    $paginacion.on('click', '.boton-pagina', function () {
        clearTimeout(estado.timerPagina);
        const destino = Number($(this).data('pagina'));
        if (destino && destino !== estado.pagina) {
            cargarPersonas(destino);
        }
    });

    $paginacion.on('input', '#input-pagina', function () {
        const $input = $(this);
        clearTimeout(estado.timerPagina);
        estado.timerPagina = setTimeout(function () {
            irAPaginaIngresada($input);
        }, 600);
    });

    $paginacion.on('keydown', '#input-pagina', function (evento) {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            clearTimeout(estado.timerPagina);
            irAPaginaIngresada($(this));
        }
    });

    $paginacion.on('blur', '#input-pagina', function () {
        clearTimeout(estado.timerPagina);
        irAPaginaIngresada($(this));
    });

    cargarPersonas(1);

    // Formulario
    function manejarEnvio(evento) {
        evento.preventDefault();
        if (estado.enviando) return;

        ocultarAlerta($alertaFormulario);
        limpiarErroresCampos();

        const datos = {
            nombre: String(campos.nombre.$input.val() || ''),
            email: String(campos.email.$input.val() || ''),
            telefono: String(campos.telefono.$input.val() || '')
        };

        const errores = validarDatos(datos);
        if (Object.keys(errores).length > 0) {
            mostrarErroresCampos(errores);
            mostrarAlerta($alertaFormulario, 'Revisá los campos marcados antes de guardar.', 'error');
            return;
        }

        guardarPersona(datos);
    }

    // Valida nombre, email y teléfono opcional con las mismas regex del backend.
    function validarDatos(datos) {
        const errores = {};
        const nombre = datos.nombre.trim();
        const email = datos.email.trim();

        if (nombre === '') {
            errores.nombre = 'El nombre es obligatorio y no puede contener solo espacios.';
        } else if (!REGEX_NOMBRE.test(nombre)) {
            errores.nombre = 'El nombre solo puede contener letras y espacios (se permiten tildes y ñ).';
        }

        if (email === '') {
            errores.email = 'El email es obligatorio.';
        } else if (!REGEX_EMAIL.test(email)) {
            errores.email = 'El email ingresado no tiene un formato válido.';
        }

        if (datos.telefono !== '' && !REGEX_TELEFONO.test(datos.telefono)) {
            errores.telefono = 'El teléfono debe tener exactamente 9 dígitos numéricos comenzando con 09, sin espacios, guiones ni +598 (ej.: 097680102).';
        }

        return errores;
    }

    // Envía la persona por AJAX y recarga la página 1 destacando el nuevo registro.
    function guardarPersona(datos) {
        alternarBotonGuardando(true);

        $.ajax({
            url: API_PERSONAS,
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                nombre: datos.nombre.trim(),
                email: datos.email.trim(),
                telefono: datos.telefono === '' ? null : datos.telefono
            }),
            success: function (respuesta) {
                $formulario[0].reset();
                limpiarErroresCampos();

                const mensaje = respuesta?.mensaje || 'Persona registrada correctamente.';
                const idNuevo = respuesta?.dato?.id ?? null;

                mostrarAlerta($alertaFormulario, mensaje, 'exito');
                cargarPersonas(1, idNuevo);
            },
            error: function (xhr) {
                const respuesta = xhr.responseJSON;
                if (respuesta?.errores) {
                    mostrarErroresCampos(respuesta.errores);
                }

                const mensaje = respuesta?.mensaje || 'No se pudo guardar el registro. Intentá nuevamente.';
                mostrarAlerta($alertaFormulario, mensaje, 'error');
            },
            complete: function () {
                alternarBotonGuardando(false);
            }
        });
    }

    function limpiarErroresCampos() {
        for (const campo of Object.values(campos)) {
            campo.$input.removeClass('es-invalido');
            campo.$error.text('');
        }
    }

    function mostrarErroresCampos(errores) {
        for (const [nombreCampo, mensaje] of Object.entries(errores)) {
            if (!campos[nombreCampo]) continue;
            campos[nombreCampo].$input.addClass('es-invalido');
            campos[nombreCampo].$error.text(mensaje);
        }
    }

    function alternarBotonGuardando(cargando) {
        estado.enviando = cargando;
        $botonGuardar.prop('disabled', cargando);
        $textoBoton.toggleClass('d-none', cargando);
        $cargandoBoton.toggleClass('d-none', !cargando);
    }

    // Listado y paginación
    // Trae la página indicada por AJAX y actualiza listado y controles.
    function cargarPersonas(pagina = 1, idDestacado = null) {
        ocultarAlerta($alertaListado);

        $.ajax({
            url: API_PERSONAS,
            method: 'GET',
            data: { pagina },
            dataType: 'json',
            success: function (respuesta) {
                if (!respuesta?.exito) {
                    mostrarAlerta($alertaListado, 'No se pudo obtener el listado de personas.', 'error');
                    return;
                }

                estado.pagina = Number(respuesta.paginacion?.pagina || pagina);
                estado.totalPaginas = Number(respuesta.paginacion?.total_paginas || 1);

                dibujarListado(respuesta.datos, idDestacado);
                dibujarPaginacion(respuesta.paginacion);
            },
            error: function (xhr) {
                const mensaje = xhr.responseJSON?.mensaje || 'No se pudo conectar con el servidor para cargar el listado.';
                mostrarAlerta($alertaListado, mensaje, 'error');
                $listado.html('<div class="estado-vacio">Error al cargar los registros.</div>');
            }
        });
    }

    function dibujarListado(personas, idDestacado) {
        if (!Array.isArray(personas) || personas.length === 0) {
            $listado.html('<div class="estado-vacio">Todavía no hay personas registradas. Completá el formulario para agregar la primera.</div>');
            return;
        }

        const filasHtml = personas.map(function (persona) {
            const esNueva = idDestacado !== null && Number(persona.id) === Number(idDestacado);
            const inicial = escaparHtml((persona.nombre || '').trim().charAt(0).toUpperCase() || '?');
            const telefono = persona.telefono
                ? `<span class="chip-telefono">${escaparHtml(persona.telefono)}</span>`
                : '<span class="sin-dato">Sin teléfono</span>';

            return `
                <div class="grilla-listado fila-persona${esNueva ? ' fila-nueva' : ''}">
                    <div class="celda-dato dato-nombre">
                        <div class="etiqueta-movil">Nombre</div>
                        <div class="bloque-nombre">
                            <div class="avatar-inicial" aria-hidden="true">${inicial}</div>
                            <span>${escaparHtml(persona.nombre)}</span>
                        </div>
                    </div>
                    <div class="celda-dato">
                        <div class="etiqueta-movil">Email</div>
                        ${escaparHtml(persona.email)}
                    </div>
                    <div class="celda-dato">
                        <div class="etiqueta-movil">Teléfono</div>
                        ${telefono}
                    </div>
                    <div class="celda-dato">
                        <div class="etiqueta-movil">Fecha de registro</div>
                        ${escaparHtml(formatearFecha(persona.fecha_creacion))}
                    </div>
                </div>
            `;
        });

        $listado.html(filasHtml.join(''));
    }

    function dibujarPaginacion(paginacion) {
        if (!paginacion) {
            $paginacion.addClass('d-none').empty();
            return;
        }

        const total = Number(paginacion.total || 0);
        const { pagina, totalPaginas } = estado;

        $contador.text(total === 1 ? '1 registro' : `${total} registros`);

        if (totalPaginas <= 1) {
            $paginacion.addClass('d-none').empty();
            return;
        }

        const botones = [
            `<button type="button" class="boton-pagina" data-pagina="${pagina - 1}"${pagina <= 1 ? ' disabled' : ''}>Anterior</button>`
        ];

        for (const item of calcularPaginasVisibles(pagina, totalPaginas)) {
            if (item === '...') {
                botones.push('<span class="separador-paginas" aria-hidden="true">…</span>');
            } else {
                botones.push(
                    `<button type="button" class="boton-pagina${item === pagina ? ' activa' : ''}" data-pagina="${item}">${item}</button>`
                );
            }
        }

        botones.push(
            `<button type="button" class="boton-pagina" data-pagina="${pagina + 1}"${pagina >= totalPaginas ? ' disabled' : ''}>Siguiente</button>`
        );

        $paginacion.removeClass('d-none').html(`
            <div class="resumen-paginacion">
                <span>Página</span>
                <input type="text" id="input-pagina" class="input-pagina" inputmode="numeric" value="${pagina}" aria-label="Número de página">
                <span>de ${totalPaginas}</span>
            </div>
            <div class="controles-paginacion">${botones.join('')}</div>
        `);
    }

    // Calcula los botones visibles con puntos suspensivos cuando hay más de 5 páginas.
    function calcularPaginasVisibles(actual, total) {
        if (total <= 5) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }

        const candidatas = [1, actual - 1, actual, actual + 1, total]
            .filter(n => n >= 1 && n <= total);
        const unicas = [...new Set(candidatas)].sort((a, b) => a - b);

        const paginas = [];
        for (const num of unicas) {
            if (paginas.length > 0 && num - paginas[paginas.length - 1] > 1) {
                paginas.push('...');
            }
            paginas.push(num);
        }

        return paginas;
    }

    // Acota el número ingresado entre 1 y la última página antes de navegar.
    function irAPaginaIngresada($input) {
        const texto = String($input.val() || '').trim();
        if (!/^-?\d+$/.test(texto)) {
            $input.val(estado.pagina);
            return;
        }

        const numero = parseInt(texto, 10);
        const destino = Math.min(Math.max(numero, 1), estado.totalPaginas);

        $input.val(destino);
        if (destino !== estado.pagina) {
            cargarPersonas(destino);
        }
    }

    // Alertas y utilidades
    // Muestra la alerta con botón de cierre y temporizador de 5 segundos.
    function mostrarAlerta($alerta, mensaje, tipo) {
        ocultarAlerta($alerta);

        $alerta
            .addClass(tipo === 'exito' ? 'es-exito' : 'es-error')
            .removeClass('d-none')
            .html(`
                <div class="alerta-contenido">
                    <div class="alerta-texto">${escaparHtml(mensaje)}</div>
                    <button type="button" class="alerta-cerrar" aria-label="Cerrar mensaje">&times;</button>
                </div>
                <div class="alerta-barra-tiempo" aria-hidden="true"></div>
            `);

        const timer = setTimeout(() => ocultarAlerta($alerta), DURACION_ALERTA);
        $alerta.data('timer', timer);
    }

    function ocultarAlerta($alerta) {
        clearTimeout($alerta.data('timer'));
        $alerta.removeData('timer').addClass('d-none').removeClass('es-exito es-error').empty();
    }

    // Convierte la fecha UTC de MySQL (YYYY-MM-DD HH:mm:ss) a hora local DD/MM/YYYY HH:mm.
    function formatearFecha(fechaTexto) {
        if (!fechaTexto) return '';

        const texto = String(fechaTexto).trim();
        const isoUtc = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(texto)
            ? texto.replace(' ', 'T') + 'Z'
            : texto;

        const fecha = new Date(isoUtc);
        if (Number.isNaN(fecha.getTime())) {
            return texto;
        }

        const dia = String(fecha.getDate()).padStart(2, '0');
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const anio = fecha.getFullYear();
        const hora = String(fecha.getHours()).padStart(2, '0');
        const minutos = String(fecha.getMinutes()).padStart(2, '0');

        return `${dia}/${mes}/${anio} ${hora}:${minutos}`;
    }

    // Escapa caracteres HTML antes de insertar datos en el DOM.
    function escaparHtml(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
});
