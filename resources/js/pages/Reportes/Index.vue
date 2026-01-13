<script setup lang="ts">
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue'
import { toast } from 'vue-sonner'
import { Bar } from 'vue-chartjs'
import {
    Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale
} from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableCaption } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Check, ChevronsUpDown, Search, Trash2 } from "lucide-vue-next"
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger } from "@/components/ui/combobox"

type Row = Record<string, any>

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reportes',
        href: '/reportes',
    },
];

const props = defineProps<{
    mode: 'all_casinos' | 'casino' | 'sucursal' | 'maquina',
    inicio: string, fin: string,
    resumenGlobal: { neto_final: number, neto_inicial: number, creditos: number, recaudo: number, gastos: number, saldo: number },
    gastosPorTipo: Row[], // Ahora contiene gastos detallados en modo sucursal
    tablaGastosAgrupados?: Row[], // Nueva prop para gastos agrupados
    tablaRetenciones?: { total_retenciones: number, cantidad_retenciones: number } | null,
    tablaBases?: { base_monedas: number, base_billetes: number, total_base: number, sucursal_nombre: string } | null,
    tablaPrincipal: Row[],
    tablaSecundaria: Row[],
    chart: { labels: string[], data: number[], title: string },

    casinos: { id: number, nombre: string }[],
    sucursales: { id: number, nombre: string, casino_id: number }[],
    maquinas: { id: number, ndi: string, nombre: string, denominacion: number; sucursal_id: number }[],
    filtros: { casino_id: number | null, sucursal_id: number | null, maquina_id: number | null, range: string, start_date?: string, end_date?: string },
    user: { id: number, roles: string[], casino_id?: number | null, sucursal_id?: number | null },
    reporteCasino?: any // 👈 Nueva prop para el reporte personalizado
}>(
)

const role = computed(() => props.user.roles[0] ?? '')


// 📅 Obtener fecha actual en formato YYYY-MM-DD
const getFechaActual = () => {
    const hoy = new Date();
    const year = hoy.getFullYear();
    const month = String(hoy.getMonth() + 1).padStart(2, '0');
    const day = String(hoy.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// form de filtros
const form = ref({
    mode: props.mode,
    casino_id: props.filtros.casino_id ?? '',
    sucursal_id: props.filtros.sucursal_id ?? '',
    maquina_id: props.filtros.maquina_id ?? '',
    range: props.filtros.range ?? 'custom', // 🎯 Predeterminado: custom
    start_date: props.filtros.start_date ?? getFechaActual(), // 🎯 Fecha actual por defecto
    end_date: props.filtros.end_date ?? getFechaActual(), // 🎯 Fecha actual por defecto
})


const sucursalesFiltradas = computed(() => {
    if (!form.value.casino_id) return props.sucursales
    return props.sucursales.filter(s => s.casino_id === Number(form.value.casino_id))
})

// Filtrar máquinas según sucursal seleccionada
const maquinasFiltradas = computed(() => {
    if (role.value === 'master_admin' || role.value === 'casino_admin') {
        return props.maquinas.filter(m => m.sucursal_id == form.value.sucursal_id)
    }
    if (role.value === 'sucursal_admin' || role.value === 'cajero') {
        return props.maquinas.filter(m => m.sucursal_id == props.user.sucursal_id)
    }
    return []
})

// Buscar máquina seleccionada
const maquinaSeleccionada = computed(() =>
    props.maquinas.find(m => m.id == form.value.maquina_id)
)

const actualizar = () => {
    // 🧹 Limpiar máquina cuando cambia casino, sucursal o modo
    form.value.maquina_id = ''

    if (form.value.range !== 'custom') {
        router.get('/reportes', form.value, { preserveState: true, replace: true })
    } else {
        aplicarRango()
    }
}
const aplicarRango = () => {
    if (!form.value.start_date || !form.value.end_date) {
        toast.error('Selecciona ambas fechas')
        return
    }
    router.get('/reportes', { ...form.value, range: 'custom' }, { replace: true })
}

// gráfico
const chartData = computed(() => ({
    labels: props.chart.labels ?? [],
    datasets: [{ label: props.chart.title, data: props.chart.data ?? [], backgroundColor: 'rgba(99,102,241,.75)', borderRadius: 6 }]
}))
const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }

// helper clases
const money = (v: number) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v)
const rojoSiNegativo = (v: number) => (v < 0 ? 'text-red-500 font-semibold' : '')

const exportar = async (headings: string[], rows: any[], nombre = 'reporte.xlsx') => {
    try {
        const response = await axios.post(
            '/reportes/export',
            {
                headings,
                rows,
                name: nombre,
            },
            {
                responseType: 'blob',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                },
            }
        )

        // Crear URL del blob
        const url = window.URL.createObjectURL(new Blob([response.data]));

        // Descargar
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', nombre);
        document.body.appendChild(link);
        link.click();

        // limpiar
        link.remove();
        window.URL.revokeObjectURL(url);

    } catch (error) {
        console.error(error);
        toast.error('Error exportando el archivo')
    }
}


// construir matrices exportables según se muestran
const exportResumen = () => {
    const h = ['Neto Final', 'Neto Inicial', 'Créditos', 'Recaudo', 'Gastos', 'Saldo']
    const r = [[props.resumenGlobal.neto_final, props.resumenGlobal.neto_inicial, props.resumenGlobal.creditos, props.resumenGlobal.recaudo, props.resumenGlobal.gastos, props.resumenGlobal.saldo]]
    exportar(h, r, 'resumen.xlsx')
}


const exportGastos = () => {

    let headings: string[] = []
    let rows: any[] = []

    // 🟣 Modo agrupado: all_casinos / casino
    if (form.value.mode === 'all_casinos' || form.value.mode === 'casino') {
        headings = ['Sucursal', 'Total Gastos']
        rows = props.gastosPorTipo.map(g => [
            g.sucursal,
            g.total
        ])
    }

    // 🟢 Modo detallado: sucursal / maquina
    else {
        headings = ['Fecha', 'Sucursal', 'Tipo', 'Proveedor', 'Descripción', 'Total']
        rows = props.gastosPorTipo.map(g => [
            g.fecha,
            g.sucursal,
            g.tipo,
            g.proveedor,
            g.descripcion,
            g.total
        ])
    }

    exportar(headings, rows, 'gastos.xlsx')
}

const exportGastosAgrupados = () => {
    if (!props.tablaGastosAgrupados) return
    const headings = ['Tipo de Gasto', 'Cantidad', 'Total', '%']
    const rows = props.tablaGastosAgrupados.map(g => [
        g.tipo,
        g.cantidad,
        g.total,
        g.porcentaje + '%'
    ])
    exportar(headings, rows, 'gastos_agrupados.xlsx')
}



const exportTablaPrincipal = () => {
    let h: string[] = []; let r: any[] = []
    if (form.value.mode === 'all_casinos') {
        h = ['Casino', 'Recaudo', 'Gastos', 'Total Neto']
        r = props.tablaPrincipal.map(x => [x.casino, x.recaudo, x.gastos, x.total_neto])
    } else if (form.value.mode === 'casino') {
        // SIMPLIFICADO
        h = ['Sucursal', 'Recaudo', 'Gastos', 'Total Neto']
        r = props.tablaPrincipal.map(x => [x.sucursal, x.recaudo, x.gastos, x.total_neto])
    } else if (form.value.mode === 'sucursal') {
        h = ['Máquina', 'Entrada', 'Salida', 'Jackpots', 'Neto Final', 'Neto Inicial', 'Créditos', 'Recaudo']
        r = props.tablaPrincipal.map(x => [x.maquina, x.entrada, x.salida, x.jackpots, x.neto_final, x.neto_inicial, x.creditos, x.recaudo])
    } else {
        h = ['Fecha', 'Máquina', 'Entrada', 'Salida', 'Jackpots', 'Neto Final', 'Neto Inicial', 'Créditos', 'Recaudo']
        r = props.tablaPrincipal.map(x => [x.fecha, `${x.maquina?.ndi} - ${x.maquina?.nombre}`, x.entrada, x.salida, x.jackpots, x.neto_final, x.neto_inicial, x.total_creditos, x.total_recaudo])
    }
    exportar(h, r, 'tabla_principal.xlsx')
}
const exportTablaSecundaria = () => {
    if (!props.tablaSecundaria?.length) return
    const h = ['Usuario', 'Neto Final', 'Neto Inicial', 'Créditos', 'Recaudo', '% Recaudado']
    const r = props.tablaSecundaria.map(x => [x.usuario, x.neto_final, x.neto_inicial, x.creditos, x.recaudo, x.porcentaje])
    exportar(h, r, 'por_usuario.xlsx')
}

const exportRetenciones = () => {
    if (!props.tablaRetenciones) return
    const h = ['Concepto', 'Valor']
    const r = [
        ['Cantidad de Retenciones', props.tablaRetenciones.cantidad_retenciones],
        ['Total Retenciones', props.tablaRetenciones.total_retenciones]
    ]
    exportar(h, r, 'retenciones.xlsx')
}

const exportBases = () => {
    if (!props.tablaBases) return
    const h = ['Concepto', 'Valor']
    const r = [
        ['Base Monedas', props.tablaBases.base_monedas],
        ['Base Billetes', props.tablaBases.base_billetes],
        ['Total Base', props.tablaBases.total_base]
    ]
    exportar(h, r, 'bases_sucursal.xlsx')
}


const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(value);
};

// 💰 Nueva función: Formato de moneda SIN el signo $
// Úsala donde necesites solo el número formateado sin el símbolo de pesos
const formatCurrencyNoSymbol = (value: number) => {
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

const formatNumber = (value: number | string) => {
    // 1. Aseguramos que el valor sea numérico
    const numberValue = parseFloat(value.toString());

    // 2. Si no es un número válido, devolvemos 0 o lo que prefieras
    if (isNaN(numberValue)) {
        return 0;
    }

    // 3. ¡LA MAGIA! toFixed(2) lo convierte a "100.00" o "123.45".
    //    Number() lo vuelve a convertir en número, eliminando los ceros innecesarios.
    return Number(numberValue.toFixed(2));
};

const exportReporteCasino = () => {
    if (!props.reporteCasino) return

    // Construir la matriz
    // 1. Headers: Concepto, [Suc1, Suc2, ...], Total
    const headerRow = ['CONCEPTO', ...props.reporteCasino.sucursales.map((s:any) => s.nombre), 'TOTAL']
    
    // 0. Contexto (Filas previas)
    // Buscamos el nombre del casino seleccionado o "Todos"
    let casinoNombre = 'Todos los Casinos';
    if (form.value.casino_id) {
        const c = props.casinos.find(x => x.id === Number(form.value.casino_id));
        if (c) casinoNombre = c.nombre;
    }
    
    // UVT Info
    const uvtInfo = `Año: ${props.reporteCasino.config_iva?.anio} | UVT: ${money(props.reporteCasino.config_iva?.valor_uvt)} | Cant: ${props.reporteCasino.config_iva?.cantidad_uvt} | %IVA: ${props.reporteCasino.config_iva?.porcentaje_iva}%`;

    const rows = [
        ['REPORTE DE RECAUDO CASINO', ''],
        ['CASINO:', casinoNombre],
        ['FECHAS:', `${props.inicio} al ${props.fin}`],
        ['DATOS FISCALES:', uvtInfo],
        [''], // Espacio antes de la tabla real
    ];

    // 2. Maquinas
    const rowMaquinas = ['N° MÁQUINAS']
    props.reporteCasino.sucursales.forEach((s:any) => rowMaquinas.push(props.reporteCasino.maquinas.values[s.id]))
    rowMaquinas.push(props.reporteCasino.maquinas.total)
    rows.push(rowMaquinas)

    rows.push([]) // Espacio

    // 3. Financiero
    const rowVentaNeta = ['VENTA NETA']
    props.reporteCasino.sucursales.forEach((s:any) => rowVentaNeta.push(props.reporteCasino.financiero.venta_neta.values[s.id]))
    rowVentaNeta.push(props.reporteCasino.financiero.venta_neta.total)
    rows.push(rowVentaNeta)

    const rowIva = ['IVA']
    props.reporteCasino.sucursales.forEach((s:any) => rowIva.push(props.reporteCasino.financiero.iva.values[s.id]))
    rowIva.push(props.reporteCasino.financiero.iva.total)
    rows.push(rowIva)

    const rowVentaMasIva = ['VENTA + IVA']
    props.reporteCasino.sucursales.forEach((s:any) => rowVentaMasIva.push(props.reporteCasino.financiero.venta_mas_iva.values[s.id]))
    rowVentaMasIva.push(props.reporteCasino.financiero.venta_mas_iva.total)
    rows.push(rowVentaMasIva)

    rows.push([]) // Espacio

    // 4. Gastos Detallados
    props.reporteCasino.gastos_detalla.forEach((g:any) => {
        const r = [g.nombre]
        props.reporteCasino.sucursales.forEach((s:any) => r.push(g.values[s.id]))
        r.push(g.total)
        rows.push(r)
    })

    // 5. Total Gastos
    const rowTotalGastos = ['TOTAL GASTOS']
    props.reporteCasino.sucursales.forEach((s:any) => rowTotalGastos.push(props.reporteCasino.total_gastos.values[s.id]))
    rowTotalGastos.push(props.reporteCasino.total_gastos.total)
    rows.push(rowTotalGastos)

    rows.push([]) // Espacio

    // 6. Especiales
    const rowCons = ['CONSIGNACIONES']
    props.reporteCasino.sucursales.forEach((s:any) => rowCons.push(props.reporteCasino.especiales.consignaciones.values[s.id]))
    rowCons.push(props.reporteCasino.especiales.consignaciones.total)
    rows.push(rowCons)

    const rowQr = ['CODIGOS QR']
    props.reporteCasino.sucursales.forEach((s:any) => rowQr.push(props.reporteCasino.especiales.qr.values[s.id]))
    rowQr.push(props.reporteCasino.especiales.qr.total)
    rows.push(rowQr)

    rows.push([]) // Espacio

    // 7. Saldo
    const rowSaldo = ['SALDO']
    props.reporteCasino.sucursales.forEach((s:any) => rowSaldo.push(props.reporteCasino.saldos_finales.saldo.values[s.id]))
    rowSaldo.push(props.reporteCasino.saldos_finales.saldo.total)
    rows.push(rowSaldo)
    
     // 8. Porcentajes
    const rowPct1 = ['% Gastos / Venta+IVA']
    props.reporteCasino.sucursales.forEach((s:any) => rowPct1.push(formatNumber(props.reporteCasino.saldos_finales.porcentaje_gastos.values[s.id]) + '%'))
    rowPct1.push(formatNumber(props.reporteCasino.saldos_finales.porcentaje_gastos.total) + '%')
    rows.push(rowPct1)

    const rowPct2 = ['% Utilidad']
    props.reporteCasino.sucursales.forEach((s:any) => rowPct2.push(formatNumber(props.reporteCasino.saldos_finales.porcentaje_utilidad.values[s.id]) + '%'))
    rowPct2.push(formatNumber(props.reporteCasino.saldos_finales.porcentaje_utilidad.total) + '%')
    rows.push(rowPct2)

    // Insertar headerRow en la posición correcta (después del contexto)
    // El orden deseado es: Contexto -> Headers -> Data
    // Actualmente 'rows' comienza con el contexto.
    // Vamos a inyectar 'headerRow' después de la fila de espacio vacía (que es la 5ta fila aprox)
    
    // rows[4] es empty. Insertamos en index 5.
    rows.splice(5, 0, headerRow);

    // Pasamos [] como headings porque ya los incluimos dentro de rows
    exportar([], rows, 'reporte_casino_detallado.xlsx')
}

</script>

<template>

    <Head title="Reportes" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-2xl font-bold">📑 Reportes</h1>


            <!-- 🔹 CONTENEDOR GENERAL DE LOS FILTROS -->
            <div class="p-6 rounded-xl shadow 
            bg-gradient-to-br from-slate-800 to-slate-900 
            border border-slate-700 space-y-6">

                <!-- 🟣 Fila 1 -> RANGO FECHAS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <!-- Selector de rango -->
                    <div>
                        <label class="text-sm font-semibold text-slate-300">Rango de fechas</label>
                        <select v-model="form.range" @change="actualizar" class="mt-1 w-full px-3 py-2 rounded border border-slate-600 
                       bg-slate-800 text-slate-100 focus:border-indigo-400">
                            <option value="custom">Fechas seleccionadas</option>     
                            <option value="today">Hoy</option>
                            <option value="yesterday">Ayer</option>                            
                            <option value="last7">Últimos 7 días</option>
                            <option value="last30">Últimos 30 días</option>
                            <option value="this_month">Este mes</option>
                            <option value="last_month">Mes pasado</option>
                            <option value="this_month_last_year">Este mes el año pasado</option>
                            <option value="this_year">Este año</option>
                            <option value="last_year">Año pasado</option>
                        </select>
                    </div>

                    <!-- Fecha Desde -->
                    <div v-if="form.range === 'custom'">
                        <label class="text-sm font-semibold text-slate-300">Desde</label>
                        <input type="date" v-model="form.start_date" class="mt-1 w-full px-3 py-2 rounded border border-slate-600 
                       bg-slate-800 text-slate-100 focus:border-indigo-400" />
                    </div>

                    <!-- Fecha Hasta -->
                    <div v-if="form.range === 'custom'">
                        <label class="text-sm font-semibold text-slate-300">Hasta</label>
                        <input type="date" v-model="form.end_date" class="mt-1 w-full px-3 py-2 rounded border border-slate-600 
                       bg-slate-800 text-slate-100 focus:border-indigo-400" />
                    </div>

                </div>

                <!-- 🟣 Fila 2 -> Casino y sucursal -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Casino -->
                    <div v-if="role === 'master_admin'">
                        <label class="text-sm font-semibold text-slate-300">Casino</label>
                        <select v-model="form.casino_id" @change="actualizar" class="mt-1 w-full px-3 py-2 rounded border border-slate-600 
                       bg-slate-800 text-slate-100 focus:border-indigo-400">
                            <option value="">Todos</option>
                            <option v-for="c in props.casinos" :key="c.id" :value="c.id">
                                {{ c.nombre }}
                            </option>
                        </select>
                    </div>

                    <!-- Sucursal -->
                    <div v-if="role === 'master_admin' || role === 'casino_admin'">
                        <label class="text-sm font-semibold text-slate-300">Sucursal</label>
                        <select v-model="form.sucursal_id" @change="actualizar" class="mt-1 w-full px-3 py-2 rounded border border-slate-600 
                       bg-slate-800 text-slate-100 focus:border-indigo-400">
                            <option value="">Todas</option>
                            <option v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">
                                {{ s.nombre }}
                            </option>
                        </select>
                    </div>

                </div>

                <!-- 🟣 Fila 3 -> Máquina -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-slate-300">Maquinas</label>
                        <Combobox v-model="form.maquina_id" by="id" class="w-full">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child class="border w-full">
                                    <Button variant="outline" class="justify-between w-full">
                                        <template v-if="maquinaSeleccionada">
                                            {{ maquinaSeleccionada.ndi }} - {{ maquinaSeleccionada.nombre }} • Den: {{ formatNumber(maquinaSeleccionada.denominacion) }}
                                        </template>
                                        <template v-else>
                                            Seleccione máquina
                                        </template>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>

                            <ComboboxList class="w-[var(--radix-popper-anchor-width)]">

                                <div class="relative w-full items-center">
                                    <ComboboxInput
                                        class="pl-9 focus-visible:ring-0 border-0 border-b rounded-none h-10 w-full"
                                        placeholder="Buscar máquina..." />
                                    <span class="absolute start-0 inset-y-0 flex items-center justify-center px-3">
                                        <Search class="size-4 text-muted-foreground" />
                                    </span>
                                </div>

                                <ComboboxEmpty>No se encontraron máquinas.</ComboboxEmpty>

                                <ComboboxGroup>
                                    <ComboboxItem v-for="m in maquinasFiltradas" :key="m.id" :value="m.id">
                                        {{ m.ndi }} - {{ m.nombre }}  • Den: {{ formatNumber(m.denominacion) }}
                                        <ComboboxItemIndicator>
                                            <Check class="ml-auto h-4 w-4" />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                    </div>
                </div>

                <!-- 🟣 Fila 4 → Botones -->
                <div class="flex flex-wrap gap-4 pt-3">

                    <button v-if="role === 'master_admin' || role === 'casino_admin'"
                        @click="form.mode = 'all_casinos'; actualizar()"
                        :class="['px-3 py-2 rounded-lg border', form.mode === 'all_casinos' ? 'bg-indigo-600 text-white' : 'bg-background']">
                        Todos los casinos
                    </button>

                    <button v-if="role === 'master_admin' || role === 'casino_admin'"
                        @click="form.mode = 'casino'; actualizar()"
                        :class="['px-3 py-2 rounded-lg border', form.mode === 'casino' ? 'bg-indigo-600 text-white' : 'bg-background']">
                        Por casino
                    </button>

                    <button @click="form.mode = 'sucursal'; actualizar()"
                        :class="['px-3 py-2 rounded-lg border', form.mode === 'sucursal' ? 'bg-indigo-600 text-white' : 'bg-background']">
                        Por sucursal
                    </button>

                    <button @click="form.mode = 'maquina'; actualizar()"
                        :class="['px-3 py-2 rounded-lg border', form.mode === 'maquina' ? 'bg-indigo-600 text-white' : 'bg-background']">
                        Por máquina
                    </button>
                </div>

            </div>


            <!-- Resumen Global -->
            <div class="p-4 rounded-lg shadow border 
            bg-gradient-to-br from-indigo-600/30 to-indigo-900/20 
            border-indigo-500/40">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold">Resumen ({{ props.inicio }} → {{ props.fin }})</h2>
                    <button @click="exportResumen" class="text-sm px-3 py-1 rounded border">Exportar</button>
                </div>
                <div class="grid md:grid-cols-6 gap-3 text-sm">
                    <div>
                        <div class="text-muted-foreground">Neto Final</div>
                        <div>{{ formatCurrencyNoSymbol(props.resumenGlobal.neto_final) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Neto Inicial</div>
                        <div>{{ formatCurrencyNoSymbol(props.resumenGlobal.neto_inicial) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Créditos</div>
                        <div>{{ formatCurrencyNoSymbol(props.resumenGlobal.creditos) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Recaudo</div>
                        <div :class="rojoSiNegativo(props.resumenGlobal.recaudo)">{{ money(props.resumenGlobal.recaudo)
                            }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Gastos</div>
                        <div>{{ money(props.resumenGlobal.gastos) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Recaudo - Gastos</div>
                        <div :class="rojoSiNegativo(props.resumenGlobal.saldo)">{{ money(props.resumenGlobal.saldo) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. GASTOS AGRUPADOS (Solo en modo all_casinos, casino o sucursal) -->
                <div v-if="form.mode !== 'maquina'" class="p-4 rounded-lg shadow border bg-gradient-to-br from-rose-600/30 to-rose-900/20 border-rose-500/40">
                    
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="font-semibold">
                            {{ form.mode === 'sucursal' ? 'Gastos Agrupados por Tipo' : 'Gastos por Sucursal' }}
                        </h2>
                        <button @click="form.mode === 'sucursal' ? exportGastosAgrupados() : exportGastos()" 
                            class="text-sm px-3 py-1 rounded border border-rose-500/50 hover:bg-rose-500/20">Exportar</button>
                    </div>
                    
                    <div class="bg-card/50 rounded-lg shadow border border-rose-500/20 overflow-hidden">
                        
                        <!-- Tabla Agrupada por TIPO (Sucursal) -->
                        <Table v-if="form.mode === 'sucursal'" class="min-w-[520px] w-full text-sm">
                            <thead class="bg-rose-900/20">
                                <tr class="text-left border-b border-rose-500/30">
                                    <th class="py-3 px-2">Tipo de Gasto</th>
                                    <th class="py-3 px-2 ">Cantidad</th>
                                    <th class="py-3 px-2 ">Total</th>
                                    <th class="py-3 px-2 ">%</th>
                                </tr>
                            </thead>
                            <TableBody>
                                <TableRow v-for="g in props.tablaGastosAgrupados" :key="g.tipo" class="border-b border-rose-500/10 hover:bg-rose-500/5">
                                    <TableCell class="py-2 px-2 font-medium">{{ g.tipo }}</TableCell>
                                    <TableCell class="py-2 px-2">{{ g.cantidad }}</TableCell>
                                    <TableCell class="py-2 px-2 font-bold">{{ money(g.total) }}</TableCell>
                                    <TableCell class="py-2 px-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs w-8">{{ g.porcentaje }}%</span>
                                            <div class="h-1.5 w-24 bg-rose-900/50 rounded-full overflow-hidden">
                                                <div class="h-full bg-rose-400" :style="{ width: g.porcentaje + '%' }"></div>
                                            </div>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!props.tablaGastosAgrupados?.length">
                                    <TableCell colspan="4" class="text-center py-4 text-muted-foreground">No hay datos agrupados</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Tabla Agrupada por SUCURSAL (Casino) -->
                        <Table v-else class="min-w-[520px] w-full text-sm">
                            <thead class="bg-rose-900/20">
                                <tr class="text-left border-b border-rose-500/30">
                                    <th class="py-3 px-2 ">Sucursal</th>
                                    <th class="py-3 px-2 ">Total Gastos</th>
                                </tr>
                            </thead>
                            <TableBody>
                                <TableRow v-for="g in gastosPorTipo" :key="g.sucursal" class="border-b border-rose-500/10 hover:bg-rose-500/5">
                                    <TableCell class="py-2 px-2 font-medium">{{ g.sucursal }}</TableCell>
                                    <TableCell class="py-2 px-2 font-bold">{{ money(g.total) }}</TableCell>
                                </TableRow>
                                <TableRow v-if="!gastosPorTipo.length">
                                    <TableCell colspan="2" class="text-center py-4 text-muted-foreground">No hay gastos registrados</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                    </div>
                </div>

            <!-- Retenciones (Solo en modo sucursal) -->
            <div v-if="form.mode === 'sucursal' && props.tablaRetenciones" 
                class="p-4 rounded-lg shadow border bg-gradient-to-br from-purple-600/30 to-purple-900/20 border-purple-500/40">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-purple-200">Retenciones del Período</h2>
                    <button @click="exportRetenciones" 
                        class="text-sm px-3 py-1 rounded border border-purple-500/50 hover:bg-purple-500/20">Exportar</button>
                </div>
                <div class="bg-card rounded-lg shadow border border-purple-500/20 overflow-hidden">
                    <Table class="min-w-[400px] w-full text-sm">
                        <TableBody>
                            <TableRow class="border-b border-purple-500/10 hover:bg-purple-500/5">
                                <TableCell class="py-3 px-4 font-medium text-foreground">Cantidad de Retenciones</TableCell>
                                <TableCell class="py-3 px-4 text-right font-bold text-lg">{{ props.tablaRetenciones.cantidad_retenciones }}</TableCell>
                            </TableRow>
                            <TableRow class="border-b border-purple-500/10 hover:bg-purple-500/5">
                                <TableCell class="py-3 px-4 font-medium text-foreground">Total Retenciones</TableCell>
                                <TableCell class="py-3 px-4 text-right font-bold text-lg text-purple-600 dark:text-purple-300">{{ money(props.tablaRetenciones.total_retenciones) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Bases de Monedas y Billetes (Solo en modo sucursal) -->
            <div v-if="form.mode === 'sucursal' && props.tablaBases" 
                class="p-4 rounded-lg shadow border bg-gradient-to-br from-cyan-600/30 to-cyan-900/20 border-cyan-500/40">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-cyan-200">Base de Monedas y Billetes - {{ props.tablaBases.sucursal_nombre }}</h2>
                    <button @click="exportBases" 
                        class="text-sm px-3 py-1 rounded border border-cyan-500/50 hover:bg-cyan-500/20">Exportar</button>
                </div>
                <div class="bg-card rounded-lg shadow border border-cyan-500/20 overflow-hidden">
                    <Table class="min-w-[400px] w-full text-sm">
                        <TableBody>
                            <TableRow class="border-b border-cyan-500/10 hover:bg-cyan-500/5">
                                <TableCell class="py-3 px-4 font-medium text-foreground">Base Monedas</TableCell>
                                <TableCell class="py-3 px-4 text-right font-bold text-lg">{{ money(props.tablaBases.base_monedas) }}</TableCell>
                            </TableRow>
                            <TableRow class="border-b border-cyan-500/10 hover:bg-cyan-500/5">
                                <TableCell class="py-3 px-4 font-medium text-foreground">Base Billetes</TableCell>
                                <TableCell class="py-3 px-4 text-right font-bold text-lg">{{ money(props.tablaBases.base_billetes) }}</TableCell>
                            </TableRow>
                            <TableRow class="border-b border-cyan-500/10 hover:bg-cyan-500/5">
                                <TableCell class="py-3 px-4 font-medium text-foreground">Total Base</TableCell>
                                <TableCell class="py-3 px-4 text-right font-bold text-lg text-cyan-600 dark:text-cyan-300">{{ money(props.tablaBases.total_base) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- 🎰 REPORTE PERSONALIZADO CASINO (NUEVO) -->
            <!-- ========================================================= -->
            <div v-if="form.mode === 'casino' && props.reporteCasino" class="space-y-6">
                
                <!-- 1. TABLA CONFIGURACION IVA (Header) -->
                <div class="p-4 rounded-lg shadow border bg-slate-800 border-slate-700">
                    <h2 class="font-bold text-lg mb-2 text-slate-200">Configuración Fiscal {{ props.reporteCasino.config_iva?.anio }}</h2>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                        <div class="bg-slate-700 p-2 rounded">
                            <span class="text-slate-400 block text-xs">Año</span>
                            <span class="font-bold">{{ props.reporteCasino.config_iva?.anio || 'N/A' }}</span>
                        </div>
                        <div class="bg-slate-700 p-2 rounded">
                            <span class="text-slate-400 block text-xs">Valor UVT</span>
                            <span class="font-bold">{{ money(props.reporteCasino.config_iva?.valor_uvt || 0) }}</span>
                        </div>
                         <div class="bg-slate-700 p-2 rounded">
                            <span class="text-slate-400 block text-xs">Cantidad UVT</span>
                            <span class="font-bold">{{ props.reporteCasino.config_iva?.cantidad_uvt || 0 }}</span>
                        </div>
                        <div class="bg-slate-700 p-2 rounded">
                            <span class="text-slate-400 block text-xs">Base Impuesto (UVT * Cant)</span>
                            <span class="font-bold">{{ money((props.reporteCasino.config_iva?.valor_uvt || 0) * (props.reporteCasino.config_iva?.cantidad_uvt || 0)) }}</span>
                        </div>
                        <div class="bg-slate-700 p-2 rounded">
                            <span class="text-slate-400 block text-xs">Porcentaje IVA</span>
                            <span class="font-bold">{{ props.reporteCasino.config_iva?.porcentaje_iva || 0 }}%</span>
                        </div>
                        
                         <!-- NUEVO: Iva Unitario -->
                         <div class="bg-amber-900/40 p-2 rounded border border-amber-500/30">
                            <span class="text-amber-400 block text-xs">IVA Unitario (Base * %)</span>
                            <span class="font-bold text-amber-300">{{ money((props.reporteCasino.config_iva?.valor_uvt || 0) * (props.reporteCasino.config_iva?.cantidad_uvt || 0) * ((props.reporteCasino.config_iva?.porcentaje_iva || 0)/100)) }}</span>
                        </div>

                    </div>
                    
                    <!-- Boton Exportar -->
                    <div class="mt-3 flex justify-end">
                         <button @click="exportReporteCasino" class="text-sm px-3 py-1 rounded border border-slate-500 hover:bg-slate-700 text-slate-200 flex items-center gap-2">
                             Exportar Detalle a Excel
                         </button>
                    </div>
                </div>

                <!-- 2. TABLA PIVOTE DE RESULTADOS -->
                <div class="overflow-x-auto rounded-lg border border-slate-700 shadow-xl">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-slate-900 text-slate-300 font-bold uppercase text-xs">
                            <tr>
                                <th class="py-3 px-4 border-b border-slate-700 min-w-[200px]">CONCEPTO</th>
                                <th v-for="suc in props.reporteCasino.sucursales" :key="suc.id" 
                                    class="py-3 px-2 border-b border-slate-700 text-right min-w-[120px]">
                                    {{ suc.nombre }}
                                </th>
                                <th class="py-3 px-4 border-b border-slate-700 text-right min-w-[140px] bg-slate-800">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50 bg-slate-800/50">
                            
                            <!-- MAQUINAS -->
                            <tr class="hover:bg-slate-700/30">
                                <td class="py-2 px-4 font-semibold text-slate-400">N° MÁQUINAS</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2">
                                    {{ props.reporteCasino.maquinas.values[suc.id] }}
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">{{ props.reporteCasino.maquinas.total }}</td>
                            </tr>

                            <tr><td class="py-4" :colspan="props.reporteCasino.sucursales.length + 2"></td></tr>

                            <!-- FINANCIERO -->
                            <tr class="hover:bg-slate-700/30 text-emerald-300 font-medium"> <!-- VENTANETA -->
                                <td class="py-2 px-4">VENTA NETA</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-xs">
                                    {{ money(props.reporteCasino.financiero.venta_neta.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">{{ money(props.reporteCasino.financiero.venta_neta.total) }}</td>
                            </tr>
                             <tr class="hover:bg-slate-700/30 text-amber-300 font-medium"> <!-- IVA -->
                                <td class="py-2 px-4">IVA</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-xs">
                                    {{ money(props.reporteCasino.financiero.iva.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">{{ money(props.reporteCasino.financiero.iva.total) }}</td>
                            </tr>
                             <tr class="hover:bg-slate-700/30 text-white font-bold text-base bg-slate-700/20"> <!-- VENTA + IVA -->
                                <td class="py-2 px-4">VENTA + IVA</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-xs">
                                    {{ money(props.reporteCasino.financiero.venta_mas_iva.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/50">{{ money(props.reporteCasino.financiero.venta_mas_iva.total) }}</td>
                            </tr>

                            <tr><td class="py-4" :colspan="props.reporteCasino.sucursales.length + 2"></td></tr>

                            <!-- GASTOS DETALLADOS -->
                            <tr v-for="gasto in props.reporteCasino.gastos_detalla" :key="gasto.nombre" class="hover:bg-slate-700/30 text-rose-200/80">
                                <td class="py-1 px-4 text-xs uppercase">{{ gasto.nombre }}</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 py-1">
                                    {{ money(gasto.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 py-1 font-semibold bg-slate-800/30">{{ money(gasto.total) }}</td>
                            </tr>

                            <!-- TOTAL GASTOS OPERATIVOS -->
                            <tr class="bg-rose-900/10 font-bold text-rose-300 border-t border-rose-500/30 mt-2">
                                <td class="py-2 px-4">TOTAL GASTOS</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-sm">
                                    {{ money(props.reporteCasino.total_gastos.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 bg-rose-900/20">{{ money(props.reporteCasino.total_gastos.total) }}</td>
                            </tr>

                            <tr><td class="py-4" :colspan="props.reporteCasino.sucursales.length + 2"></td></tr>

                            <!-- ESPECIALES -->
                            <tr class="hover:bg-slate-700/30 text-orange-300">
                                <td class="py-2 px-4">CONSIGNACIONES</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-xs">
                                    {{ money(props.reporteCasino.especiales.consignaciones.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">{{ money(props.reporteCasino.especiales.consignaciones.total) }}</td>
                            </tr>
                            <tr class="hover:bg-slate-700/30 text-orange-300">
                                <td class="py-2 px-4">CODIGOS QR</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-xs">
                                    {{ money(props.reporteCasino.especiales.qr.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">{{ money(props.reporteCasino.especiales.qr.total) }}</td>
                            </tr>

                            <tr><td class="py-4" :colspan="props.reporteCasino.sucursales.length + 2"></td></tr>

                            <!-- SALDOS FINALES -->
                            <tr class="bg-indigo-900/20 font-bold text-indigo-300 text-lg border-t-2 border-indigo-500/50">
                                <td class="py-3 px-4">SALDO</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2 text-sm">
                                    {{ money(props.reporteCasino.saldos_finales.saldo.values[suc.id]) }}
                                </td>
                                <td class="text-right px-4 bg-indigo-900/30">{{ money(props.reporteCasino.saldos_finales.saldo.total) }}</td>
                            </tr>

                            <!-- PORCENTAJES -->
                             <tr class="text-slate-400 font-medium">
                                <td class="py-2 px-4">% Gastos / Venta+IVA</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2">
                                    {{ formatNumber(props.reporteCasino.saldos_finales.porcentaje_gastos.values[suc.id]) }}%
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">
                                    {{ formatNumber(props.reporteCasino.saldos_finales.porcentaje_gastos.total) }}%
                                </td>
                            </tr>
                            <tr class="text-emerald-400 font-bold">
                                <td class="py-2 px-4">% Utilidad</td>
                                <td v-for="suc in props.reporteCasino.sucursales" :key="suc.id" class="text-right px-2">
                                    {{ formatNumber(props.reporteCasino.saldos_finales.porcentaje_utilidad.values[suc.id]) }}%
                                </td>
                                <td class="text-right px-4 font-bold bg-slate-800/30">
                                    {{ formatNumber(props.reporteCasino.saldos_finales.porcentaje_utilidad.total) }}%
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>

            </div>
            
            <!-- Tabla principal (según modo) -->
            <div v-if="props.tablaPrincipal.length" class="p-4 rounded-lg shadow border 
            bg-gradient-to-br from-emerald-600/30 to-emerald-900/20 
            border-emerald-500/40">

                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold">
                        {{ form.mode === 'all_casinos' ? 'Recaudo por casino' :
                            form.mode === 'casino' ? 'Recaudo por sucursal' :
                                form.mode === 'sucursal' ? 'Recaudo por máquina' :
                                    'Historial por máquina'
                        }}
                    </h2>
                    <button @click="exportTablaPrincipal" class="text-sm px-3 py-1 rounded border">Exportar</button>
                </div>

                <!-- tablas -->
                <div class="overflow-auto">
                    <!-- all_casinos (SIMPLIFICADO) -->
                    <table v-if="form.mode === 'all_casinos'"
                        class="min-w-[720px] w-full text-sm">
                        <thead class="bg-emerald-900/20">
                            <tr class="text-left border-b border-emerald-500/30">
                                <th class="py-3 px-2">Casino</th>
                                <th class="py-3 px-2">Recaudo</th>
                                <th class="py-3 px-2">Gastos</th>
                                <th class="py-3 px-2">Total Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="x.casino" class="border-b border-emerald-500/10 hover:bg-emerald-500/5">
                                <td class="py-2 px-2 font-medium">{{ x.casino }}</td>
                                <td class="py-2 px-2 text-green-400">{{ money(x.recaudo) }}</td>
                                <td class="py-2 px-2 text-rose-400">{{ money(x.gastos) }}</td>
                                <td class="py-2 px-2 font-bold text-lg" :class="rojoSiNegativo(x.total_neto)">{{ money(x.total_neto) }}</td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="4" class="py-4 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>

                     <!-- casino (SIMPLIFICADO) -->
                     <table v-else-if="form.mode === 'casino'"
                        class="min-w-[720px] w-full text-sm">
                        <thead class="bg-emerald-900/20">
                            <tr class="text-left border-b border-emerald-500/30">
                                <th class="py-3 px-2">Sucursal</th>
                                <th class="py-3 px-2">Recaudo</th>
                                <th class="py-3 px-2">Gastos</th>
                                <th class="py-3 px-2">Total Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="x.sucursal" class="border-b border-emerald-500/10 hover:bg-emerald-500/5">
                                <td class="py-2 px-2 font-medium">{{ x.sucursal }}</td>
                                <td class="py-2 px-2 text-green-400">{{ money(x.recaudo) }}</td>
                                <td class="py-2 px-2 text-rose-400">{{ money(x.gastos) }}</td>
                                <td class="py-2 px-2 font-bold text-lg" :class="rojoSiNegativo(x.total_neto)">{{ money(x.total_neto) }}</td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="4" class="py-4 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- sucursal -->
                    <table v-else-if="form.mode === 'sucursal'" class="min-w-[900px] w-full text-sm">
                        <thead class="bg-emerald-900/20">
                            <tr class="text-left border-b border-emerald-500/30">
                                <th class="py-3 px-2">Máquina</th>
                                <th class="py-3 px-2 text-right">Entrada</th>
                                <th class="py-3 px-2 text-right">Salida</th>
                                <th class="py-3 px-2 text-right">Jackpots</th>
                                <th class="py-3 px-2 text-right">Neto Final</th>
                                <th class="py-3 px-2 text-right">Neto Inicial</th>
                                <th class="py-3 px-2 text-right">Créditos</th>
                                <th class="py-3 px-2 text-right">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="x.maquina" class="border-b border-emerald-500/10 hover:bg-emerald-500/5">
                                <td class="py-2 px-2 font-medium">{{ x.maquina }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.entrada) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.salida) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.jackpots) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.neto_final) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.neto_inicial) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.creditos) }}</td>
                                <td class="py-2 px-2 font-bold text-sm text-right" :class="rojoSiNegativo(x.recaudo)">{{ money(x.recaudo) }}</td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="8" class="py-4 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- maquina -->
                    <table v-else class="min-w-[1000px] w-full text-sm">
                        <thead class="bg-emerald-900/20">
                            <tr class="text-left border-b border-emerald-500/30">
                                <th class="py-3 px-2">Fecha</th>
                                <th class="py-3 px-2">Máquina</th>
                                <th class="py-3 px-2 text-right">Entrada</th>
                                <th class="py-3 px-2 text-right">Salida</th>
                                <th class="py-3 px-2 text-right">Jackpots</th>
                                <th class="py-3 px-2 text-right">Neto Final</th>
                                <th class="py-3 px-2 text-right">Neto Inicial</th>
                                <th class="py-3 px-2 text-right">Créditos</th>
                                <th class="py-3 px-2 text-right">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="`${x.fecha}-${x.maquina_id}`" class="border-b border-emerald-500/10 hover:bg-emerald-500/5">
                                <td class="py-2 px-2">{{ x.fecha }}</td>
                                <td class="py-2 px-2 font-medium">{{ x.maquina?.ndi }} - {{ x.maquina?.nombre }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.entrada) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.salida) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.jackpots) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.neto_final) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.neto_inicial) }}</td>
                                <td class="py-2 px-2 text-xs text-right">{{ formatCurrencyNoSymbol(x.total_creditos) }}</td>
                                <td class="py-2 px-2 font-bold text-sm text-right" :class="rojoSiNegativo(x.total_recaudo)">{{ money(x.total_recaudo) }}
                                </td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="9" class="py-4 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            
            <!-- SECCIÓN GASTOS (Oculta si es modo máquina o all_casinos) -->
            <div v-if="form.mode !== 'maquina' && form.mode !== 'all_casinos'" class="space-y-4">

                <!-- 1. GASTOS DETALLADOS (Solo en modo sucursal) -->
                <div v-if="form.mode === 'sucursal'" class="p-4 rounded-lg shadow border bg-gradient-to-br from-rose-600/30 to-rose-900/20 border-rose-500/40">
                
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="font-semibold text-rose-200">Gastos Detallados</h2>
                        <button @click="exportGastos" class="text-sm px-3 py-1 rounded border border-rose-500/50 hover:bg-rose-500/20">Exportar</button>
                    </div>
                    <div class="bg-card/50 rounded-lg shadow border border-rose-500/20 overflow-hidden">
                        <Table class="min-w-[520px] w-full text-sm">
                            <thead class="bg-rose-900/20">
                                <tr class="text-left border-b border-rose-500/30">
                                    <th class="py-3 px-2 text-rose-800 dark:text-rose-100">Fecha</th>
                                    <th class="py-3 px-2 text-rose-800 dark:text-rose-100">Sucursal</th>
                                    <th class="py-3 px-2 text-rose-800 dark:text-rose-100">Tipo</th>
                                    <th class="py-3 px-2 text-rose-800 dark:text-rose-100">Proveedor</th>
                                    <th class="py-3 px-2 text-rose-800 dark:text-rose-100">Descripción</th>
                                    <th class="py-3 px-2 text-rose-800 dark:text-rose-100">Total</th>
                                </tr>
                            </thead>

                            <TableBody>
                                <TableRow v-for="g in gastosPorTipo" :key="g.id" class="border-b border-rose-500/10 hover:bg-rose-500/5">
                                    <TableCell class="py-2 px-2">{{ g.fecha }}</TableCell>
                                    <TableCell class="py-2 px-2">{{ g.sucursal }}</TableCell>
                                    <TableCell class="py-2 px-2">
                                        <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-800 dark:text-rose-200 text-xs border border-rose-500/30 font-medium">
                                            {{ g.tipo }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-2 px-2">
                                        <span class="text-emerald-700 dark:text-emerald-400 font-semibold">{{ g.proveedor || 'N/A' }}</span>
                                    </TableCell>
                                    <TableCell class="py-2 px-2 text-muted-foreground italic">{{ g.descripcion }}</TableCell>
                                    <TableCell class="py-2 px-2 font-medium">{{ formatCurrency(g.total) }}</TableCell>
                                </TableRow>
                                <TableRow v-if="!gastosPorTipo.length">
                                    <TableCell colspan="6" class="text-center py-4 text-muted-foreground">No hay gastos registrados</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

            </div>


            <!-- Tabla secundaria (por usuario en sucursal o máquina) -->
            <div v-if="props.tablaSecundaria?.length" class="bg-card rounded-xl border p-4">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold">Recaudo por usuario</h2>
                    <button @click="exportTablaSecundaria" class="text-sm px-3 py-1 rounded border">Exportar</button>
                </div>
                <div class="overflow-auto">
                    <table class="min-w-[720px] w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">Usuario</th>
                                <th class="py-2">Neto Final</th>
                                <th class="py-2">Neto Inicial</th>
                                <th class="py-2">Créditos</th>
                                <th class="py-2">Recaudo</th>
                                <th class="py-2">% Recaudado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaSecundaria" :key="x.usuario" class="border-b">
                                <td class="py-2">{{ x.usuario }}</td>
                                <td class="py-2">{{ formatCurrencyNoSymbol(x.neto_final) }}</td>
                                <td class="py-2">{{ formatCurrencyNoSymbol(x.neto_inicial) }}</td>
                                <td class="py-2">{{ formatCurrencyNoSymbol(x.creditos) }}</td>
                                <td class="py-2" :class="rojoSiNegativo(x.recaudo)">{{ money(x.recaudo) }}</td>
                                <td class="py-2">{{ x.porcentaje }}%</td>
                                <TableCell class="py-2 px-2">
                                        <div class="flex items-center gap-2">                                            
                                            <div class="h-1.5 w-24 bg-green-900/50 rounded-full overflow-hidden">
                                                <div class="h-full bg-green-400" :style="{ width: x.porcentaje + '%' }"></div>
                                            </div>
                                        </div>
                                    </TableCell>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gráfico -->
            <div v-if="props.chart?.labels?.length" class="bg-card rounded-xl border p-4 h-[420px]">
                <h2 class="font-semibold mb-2">{{ props.chart.title }}</h2>
                <div class="h-[360px]">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
