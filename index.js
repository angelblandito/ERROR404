// Tabla que se esta viendo
var tablaActual = null;

// Tablas: titulo e icono del menu, columna del ID y columnas que se mandan en el JSON
var TABLAS = {
    productos: { titulo: "Productos", icono: "bi-box-seam", id: "id_producto", campos: ["id_categoria", "id_proveedor", "codigo_sku", "nombre", "descripcion", "precio_venta"] },
    categorias: { titulo: "Categorías", icono: "bi-tags", id: "id_categoria", campos: ["nombre", "descripcion"] },
    inventario_sucursales: { titulo: "Inventario", icono: "bi-stack", id: "id_inventario", campos: ["id_sucursal", "id_producto", "stock"] },
    ventas: { titulo: "Ventas", icono: "bi-basket", id: "id_venta", campos: ["id_sucursal", "id_cliente", "id_empleado", "id_metodo", "fecha_venta", "total"] },
    detalles_venta: { titulo: "Detalles de venta", icono: "bi-receipt", id: "id_detalle", campos: ["id_venta", "id_producto", "cantidad", "precio_unitario", "subtotal"] },
    envios: { titulo: "Envíos", icono: "bi-truck", id: "id_envio", campos: ["id_venta", "direccion_entrega", "estado_envio", "numero_guia"] },
    devoluciones: { titulo: "Devoluciones", icono: "bi-arrow-counterclockwise", id: "devolucion_id", campos: ["venta_id", "cliente_id", "motivo", "monto_reembolsado", "estado_devolucion", "fecha_solicitud"] },
    cupones_descuentos: { titulo: "Cupones", icono: "bi-ticket-perforated", id: "cupon_id", campos: ["codigo", "descuento_porcentaje", "fecha_inicio", "fecha_fin", "activo"] },
    metodos_pago: { titulo: "Métodos de pago", icono: "bi-credit-card", id: "id_metodo", campos: ["metodo"] },
    clientes: { titulo: "Clientes", icono: "bi-people", id: "id_cliente", campos: ["nombre_completo", "email", "telefono", "direccion_default"] },
    resenas_productos: { titulo: "Reseñas", icono: "bi-star", id: "resena_id", campos: ["producto_id", "cliente_id", "calificacion", "comentario", "fecha_resena"] },
    empleados: { titulo: "Empleados", icono: "bi-person-badge", id: "id_empleado", campos: ["id_sucursal", "nombre_completo", "puesto", "fecha_contratacion"] },
    sucursales: { titulo: "Sucursales", icono: "bi-shop", id: "id_sucursal", campos: ["nombre", "tipo", "direccion", "telefono"] },
    proveedores: { titulo: "Proveedores", icono: "bi-building", id: "id_proveedor", campos: ["nombre_empresa", "contacto", "telefono"] },
    compras_proveedores: { titulo: "Compras", icono: "bi-cart3", id: "id_compra", campos: ["id_proveedor", "id_sucursal", "fecha_pedido", "total_compra", "estado"] },
    ordenes_compra: { titulo: "Órdenes de compra", icono: "bi-clipboard-check", id: "orden_compra_id", campos: ["proveedor_id", "fecha_orden", "total_orden", "estado"] }
};

// Tarjetas que salen en la pagina de Inicio
var TARJETAS = ["productos", "ventas", "clientes", "inventario_sucursales", "proveedores", "compras_proveedores", "empleados", "sucursales"];

// Registros que se estan mostrando en la tabla
var registrosActuales = [];

// Columnas que se muestran en verde (como los titulos de la imagen)
var COLUMNAS_VERDES = ["nombre", "nombre_completo", "nombre_empresa", "codigo", "metodo"];

// ================== INICIO ==================

// Se ejecuta cuando carga la pagina
function iniciar() {
    dibujarMenu();
    mostrarInicio();
    revisarApi();
}

// Dibuja el menu lateral con todas las tablas
function dibujarMenu() {
    var ulMenu = document.getElementById("ulMenu");

    ulMenu.innerHTML = '<li><a href="#" id="menu_inicio" onclick="mostrarInicio(); return false;"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>';

    for (var tabla in TABLAS) {
        ulMenu.innerHTML +=
            '<li><a href="#" id="menu_' + tabla + '" onclick="abrirTabla(\'' + tabla + '\'); return false;">' +
                '<i class="bi ' + TABLAS[tabla].icono + '"></i> ' + TABLAS[tabla].titulo +
            "</a></li>";
    }
}

// Marca en el menu la opcion seleccionada
function marcarMenu(opcion) {
    document.querySelectorAll(".sidebar-menu a").forEach(function (a) {
        a.classList.remove("activo");
    });
    document.getElementById("menu_" + opcion).classList.add("activo");
    document.body.classList.remove("menu-visible");
}

// Muestra la pagina de Inicio con las tarjetas
function mostrarInicio() {
    marcarMenu("inicio");
    document.getElementById("secInicio").classList.remove("d-none");
    document.getElementById("secTabla").classList.add("d-none");

    var divTarjetas = document.getElementById("divTarjetas");
    divTarjetas.innerHTML = "";

    TARJETAS.forEach(function (tabla) {
        divTarjetas.innerHTML +=
            '<div class="col-sm-6 col-lg-3">' +
                '<div class="tarjeta" onclick="abrirTabla(\'' + tabla + '\')">' +
                    "<div>" +
                        '<div class="numero" id="num_' + tabla + '">...</div>' +
                        '<div class="etiqueta">' + TABLAS[tabla].titulo + "</div>" +
                    "</div>" +
                    '<i class="bi ' + TABLAS[tabla].icono + '"></i>' +
                "</div>" +
            "</div>";

        // Cuenta los registros de cada tabla
        fetch('http://localhost:8080/' + tabla, {
            method: 'GET',
        })
        .then(response => response.json())
        .then(data => {
            var total = Array.isArray(data.data) ? data.data.length : 0;
            document.getElementById("num_" + tabla).textContent = total;

            // Suma el total de todas las ventas
            if (tabla === "ventas" && Array.isArray(data.data)) {
                var suma = 0;
                data.data.forEach(function (venta) {
                    suma += Number(venta.total);
                });
                document.getElementById("divTotalVentas").textContent = "$" + suma.toFixed(2);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById("num_" + tabla).textContent = "-";
        });
    });
}

// ================== PAGINA DE CADA TABLA ==================

// Abre la tabla elegida en el menu
function abrirTabla(tabla) {
    tablaActual = tabla;
    marcarMenu(tabla);
    document.getElementById("secInicio").classList.add("d-none");
    document.getElementById("secTabla").classList.remove("d-none");
    document.getElementById("h1Titulo").textContent = TABLAS[tabla].titulo;

    // Arma los campos del formulario (dentro de la ventana)
    var divCampos = document.getElementById("divCampos");
    divCampos.innerHTML = "";

    TABLAS[tabla].campos.forEach(function (campo) {
        divCampos.innerHTML +=
            '<div class="col-md-6">' +
                '<label for="txt_' + campo + '" class="form-label w-100">' + campo.toUpperCase() + ": " +
                    '<input type="text" id="txt_' + campo + '" class="form-control" />' +
                "</label>" +
            "</div>";
    });

    document.getElementById("txtId").value = "";
    document.getElementById("txtBuscar").value = "";
    document.getElementById("preDatos").textContent = "";
    listarDatos();
}

// Junta los datos de los inputs en un objeto para mandarlo como JSON
function leerFormulario() {
    var datos = {};

    TABLAS[tablaActual].campos.forEach(function (campo) {
        var valor = document.getElementById("txt_" + campo).value.trim();
        datos[campo] = valor === "" ? null : valor;
    });

    console.log("Datos del formulario:", datos);
    return datos;
}

// Muestra la respuesta de la API en el <pre>
function imprimirDatos(data) {
    console.log(data);
    document.getElementById("preDatos").textContent = JSON.stringify(data, null, 2);
}

// GET: trae todos los registros y los pone en la tabla
// mostrarRespuesta = false sirve para recargar la tabla sin tapar la respuesta del POST, PUT o DELETE
function listarDatos(mostrarRespuesta) {
    var tabla = tablaActual;

    fetch('http://localhost:8080/' + tabla, {
        method: 'GET',
    })
    .then(response => response.json())
    .then(data => {
        if (mostrarRespuesta !== false) {
            imprimirDatos(data);
        }
        dibujarTabla(tabla, data);
    })
    .catch(error => {
        console.error('Error:', error);
        imprimirDatos("No se pudo conectar con la API en http://localhost:8080");
        dibujarTabla(tabla, { success: false });
    });
}

// Pone los registros en la tabla HTML
function dibujarTabla(tabla, data) {
    var columnas = [TABLAS[tabla].id].concat(TABLAS[tabla].campos);
    var trEncabezado = document.getElementById("trEncabezado");
    var tbodyDatos = document.getElementById("tbodyDatos");

    // Encabezado: casilla, columnas y filtro
    trEncabezado.innerHTML = '<th class="col-check"><input type="checkbox" class="check" title="Quitar selección" onclick="quitarSeleccion(); this.checked = false;" /></th>';
    columnas.forEach(function (c) {
        var titulo = c.replace(/_/g, " ");
        trEncabezado.innerHTML += "<th>" + titulo.charAt(0).toUpperCase() + titulo.slice(1) + "</th>";
    });
    trEncabezado.innerHTML += '<th class="col-editar"><i class="bi bi-funnel"></i></th>';
    tbodyDatos.innerHTML = "";
    registrosActuales = [];

    if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
        tbodyDatos.innerHTML = '<tr><td colspan="' + (columnas.length + 2) + '" class="sin-registros">Sin registros</td></tr>';
        return;
    }

    registrosActuales = data.data;

    data.data.forEach(function (registro, indice) {
        var fila = document.createElement("tr");

        // Al dar clic en la fila (o en su casilla) se selecciona y se abre para modificar
        fila.onclick = function () { seleccionarFila(fila, registro); };

        // Casilla
        var celdaCheck = document.createElement("td");
        celdaCheck.className = "col-check";
        celdaCheck.innerHTML = '<input type="checkbox" class="check" data-indice="' + indice + '" />';
        fila.appendChild(celdaCheck);

        // Datos
        columnas.forEach(function (c) {
            var celda = document.createElement("td");
            celda.textContent = (registro[c] == null ? "" : registro[c]);
            if (COLUMNAS_VERDES.includes(c)) {
                celda.className = "celda-verde";
            }
            fila.appendChild(celda);
        });

        // Lapiz para editar
        var celdaEditar = document.createElement("td");
        celdaEditar.className = "col-editar";
        celdaEditar.title = "Editar";
        celdaEditar.innerHTML = '<i class="bi bi-pencil"></i>';

        fila.appendChild(celdaEditar);

        tbodyDatos.appendChild(fila);
    });

    filtrarTabla();
}

// Selecciona una sola fila: la marca, la resalta y abre el formulario para modificarla
function seleccionarFila(fila, registro) {
    quitarSeleccion();
    fila.classList.add("seleccionada");
    fila.querySelector(".check").checked = true;
    seleccionarRegistro(registro);
}

// Desmarca todas las filas
function quitarSeleccion() {
    document.querySelectorAll("#tbodyDatos tr").forEach(function (fila) {
        fila.classList.remove("seleccionada");
        var check = fila.querySelector(".check");
        if (check) {
            check.checked = false;
        }
    });
}

// Buscador de arriba: esconde las filas que no tienen el texto escrito
function filtrarTabla() {
    var texto = document.getElementById("txtBuscar").value.toLowerCase();

    document.querySelectorAll("#tbodyDatos tr").forEach(function (fila) {
        var coincide = fila.textContent.toLowerCase().includes(texto);
        fila.style.display = coincide ? "" : "none";
    });
}

// ================== VENTANA DEL FORMULARIO ==================

// Abre la ventana del formulario
function abrirVentana(titulo) {
    document.getElementById("h5Modal").textContent = titulo;
    bootstrap.Modal.getOrCreateInstance(document.getElementById("divModal")).show();
}

// Boton "Nuevo": abre el formulario vacio para CREAR un registro
function nuevoRegistro() {
    quitarSeleccion();
    limpiarFormulario();
    document.getElementById("preDatos").textContent = "";
    modoFormulario("nuevo");
    abrirVentana("Nuevo registro · " + TABLAS[tablaActual].titulo);
}

// Muestra solo los botones que sirven:
// 'nuevo'  = CREAR y LIMPIAR (el ID se oculta porque lo pone la base de datos)
// 'editar' = ACTUALIZAR y BORRAR (con el ID del registro seleccionado)
function modoFormulario(modo) {
    var esNuevo = modo === "nuevo";
    document.getElementById("lblId").classList.toggle("d-none", esNuevo);
    document.getElementById("btnCrear").classList.toggle("d-none", !esNuevo);
    document.getElementById("btnLimpiar").classList.toggle("d-none", !esNuevo);
    document.getElementById("btnActualizar").classList.toggle("d-none", esNuevo);
    document.getElementById("btnBorrar").classList.toggle("d-none", esNuevo);
}

// Fila seleccionada: pasa sus datos al formulario y abre la ventana para modificarla
function seleccionarRegistro(registro) {
    var id = registro[TABLAS[tablaActual].id];
    document.getElementById("txtId").value = id;

    TABLAS[tablaActual].campos.forEach(function (campo) {
        document.getElementById("txt_" + campo).value = (registro[campo] == null ? "" : registro[campo]);
    });

    document.getElementById("preDatos").textContent = "";
    modoFormulario("editar");
    abrirVentana("Modificar registro #" + id + " · " + TABLAS[tablaActual].titulo);
}

// POST: crea un registro nuevo
function crearDato() {
    fetch('http://localhost:8080/' + tablaActual, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(leerFormulario())
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            limpiarFormulario();
            listarDatos(false);
        }
        imprimirDatos(data);
    })
    .catch(error => {
        console.error('Error:', error);
        imprimirDatos("No se pudo conectar con la API en http://localhost:8080");
    });
}

// PUT: actualiza el registro del ID
function actualizarDato() {
    var id = document.getElementById("txtId").value;

    if (id === "") {
        imprimirDatos("Falta el ID: selecciona una fila de la tabla.");
        return;
    }

    fetch('http://localhost:8080/' + tablaActual + "/" + id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(leerFormulario())
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            listarDatos(false);
        }
        imprimirDatos(data);
    })
    .catch(error => {
        console.error('Error:', error);
        imprimirDatos("No se pudo conectar con la API en http://localhost:8080");
    });
}

// DELETE: borra el registro del ID
function borrarDato() {
    var id = document.getElementById("txtId").value;

    if (id === "") {
        imprimirDatos("Falta el ID: selecciona una fila de la tabla.");
        return;
    }

    fetch('http://localhost:8080/' + tablaActual + "/" + id, {
        method: 'DELETE',
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            limpiarFormulario();
            listarDatos(false);
        }
        imprimirDatos(data);
    })
    .catch(error => {
        console.error('Error:', error);
        imprimirDatos("No se pudo conectar con la API en http://localhost:8080");
    });
}

// Borra lo escrito en el formulario
function limpiarFormulario() {
    document.getElementById("txtId").value = "";

    TABLAS[tablaActual].campos.forEach(function (campo) {
        document.getElementById("txt_" + campo).value = "";
    });
}

// ================== OTROS ==================

// Boton de la flechita: muestra u oculta el menu
function mostrarOcultarMenu() {
    if (window.innerWidth <= 768) {
        document.body.classList.toggle("menu-visible");
    } else {
        document.body.classList.toggle("menu-oculto");
    }

    var oculto = document.body.classList.contains("menu-oculto") || (window.innerWidth <= 768 && !document.body.classList.contains("menu-visible"));
    document.getElementById("iFlechaMenu").className = oculto ? "bi bi-chevron-right" : "bi bi-chevron-left";
}

// Revisa si la API esta encendida
function revisarApi() {
    var spanEstado = document.getElementById("spanEstado");

    fetch('http://localhost:8080/health-check', {
        method: 'GET',
    })
    .then(response => response.json())
    .then(data => {
        spanEstado.className = "estado-api en-linea";
        spanEstado.textContent = "● API en línea";
    })
    .catch(error => {
        console.error('Error:', error);
        spanEstado.className = "estado-api apagada";
        spanEstado.textContent = "● API apagada";
    });
}