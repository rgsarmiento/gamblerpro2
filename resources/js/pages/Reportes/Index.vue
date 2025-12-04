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
    tablaPrincipal: Row[],
    tablaSecundaria: Row[],
    chart: { labels: string[], data: number[], title: string },

    casinos: { id: number, nombre: string }[],
    sucursales: { id: number, nombre: string, casino_id: number }[],
    maquinas: { id: number, ndi: string, nombre: string, denominacion: number; sucursal_id: number }[],
    filtros: { casino_id: number | null, sucursal_id: number | null, maquina_id: number | null, range: string, start_date?: string, end_date?: string },
    user: { id: number, roles: string[], casino_id?: number | null, sucursal_id?: number | null }
}>()

const role = computed(() => props.user.roles[0] ?? '')

// form de filtros
const form = ref({
    mode: props.mode,
    casino_id: props.filtros.casino_id ?? '',
    sucursal_id: props.filtros.sucursal_id ?? '',
    maquina_id: props.filtros.maquina_id ?? '',
    range: props.filtros.range ?? 'this_month',
    start_date: props.filtros.start_date ?? '',
    end_date: props.filtros.end_date ?? '',
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
        headings = ['Fecha', 'Sucursal', 'Tipo', 'Descripción', 'Total']
        rows = props.gastosPorTipo.map(g => [
            g.fecha,
            g.sucursal,
            g.tipo,
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


const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(value);
};

const formatNumber = (value) => {
    // 1. Aseguramos que el valor sea numérico
    const numberValue = parseFloat(value);

    // 2. Si no es un número válido, devolvemos 0 o lo que prefieras
    if (isNaN(numberValue)) {
        return 0;
    }

    // 3. ¡LA MAGIA! toFixed(2) lo convierte a "100.00" o "123.45".
    //    Number() lo vuelve a convertir en número, eliminando los ceros innecesarios.
    return Number(numberValue.toFixed(2));
};

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
                            <option value="today">Hoy</option>
                            <option value="yesterday">Ayer</option>
                            <option value="custom">Fechas seleccionadas</option>
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
                        <div>{{ money(props.resumenGlobal.neto_final) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Neto Inicial</div>
                        <div>{{ money(props.resumenGlobal.neto_inicial) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Créditos</div>
                        <div>{{ money(props.resumenGlobal.creditos) }}</div>
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

            <!-- 2. GASTOS AGRUPADOS (En modo sucursal o casino) -->
                <div class="p-4 rounded-lg shadow border bg-gradient-to-br from-rose-600/30 to-rose-900/20 border-rose-500/40">
                    
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

            <!-- Tabla principal (según modo) -->
            <div class="p-4 rounded-lg shadow border 
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
                                <th class="py-3 px-2">Entrada</th>
                                <th class="py-3 px-2">Salida</th>
                                <th class="py-3 px-2">Jackpots</th>
                                <th class="py-3 px-2">Neto Final</th>
                                <th class="py-3 px-2">Neto Inicial</th>
                                <th class="py-3 px-2">Créditos</th>
                                <th class="py-3 px-2">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="x.maquina" class="border-b border-emerald-500/10 hover:bg-emerald-500/5">
                                <td class="py-2 px-2 font-medium">{{ x.maquina }}</td>
                                <td class="py-2 px-2">{{ money(x.entrada) }}</td>
                                <td class="py-2 px-2">{{ money(x.salida) }}</td>
                                <td class="py-2 px-2">{{ money(x.jackpots) }}</td>
                                <td class="py-2 px-2">{{ money(x.neto_final) }}</td>
                                <td class="py-2 px-2">{{ money(x.neto_inicial) }}</td>
                                <td class="py-2 px-2">{{ money(x.creditos) }}</td>
                                <td class="py-2 px-2 font-bold" :class="rojoSiNegativo(x.recaudo)">{{ money(x.recaudo) }}</td>
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
                                <th class="py-3 px-2">Entrada</th>
                                <th class="py-3 px-2">Salida</th>
                                <th class="py-3 px-2">Jackpots</th>
                                <th class="py-3 px-2">Neto Final</th>
                                <th class="py-3 px-2">Neto Inicial</th>
                                <th class="py-3 px-2">Créditos</th>
                                <th class="py-3 px-2">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="`${x.fecha}-${x.maquina_id}`" class="border-b border-emerald-500/10 hover:bg-emerald-500/5">
                                <td class="py-2 px-2">{{ x.fecha }}</td>
                                <td class="py-2 px-2 font-medium">{{ x.maquina?.ndi }} - {{ x.maquina?.nombre }}</td>
                                <td class="py-2 px-2">{{ money(x.entrada) }}</td>
                                <td class="py-2 px-2">{{ money(x.salida) }}</td>
                                <td class="py-2 px-2">{{ money(x.jackpots) }}</td>
                                <td class="py-2 px-2">{{ money(x.neto_final) }}</td>
                                <td class="py-2 px-2">{{ money(x.neto_inicial) }}</td>
                                <td class="py-2 px-2">{{ money(x.total_creditos) }}</td>
                                <td class="py-2 px-2 font-bold" :class="rojoSiNegativo(x.total_recaudo)">{{ money(x.total_recaudo) }}
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
                                    <th class="py-3 px-2 text-rose-100">Fecha</th>
                                    <th class="py-3 px-2 text-rose-100">Sucursal</th>
                                    <th class="py-3 px-2 text-rose-100">Tipo</th>
                                    <th class="py-3 px-2 text-rose-100">Descripción</th>
                                    <th class="py-3 px-2 text-rose-100">Total</th>
                                </tr>
                            </thead>

                            <TableBody>
                                <TableRow v-for="g in gastosPorTipo" :key="g.id" class="border-b border-rose-500/10 hover:bg-rose-500/5">
                                    <TableCell class="py-2 px-2">{{ g.fecha }}</TableCell>
                                    <TableCell class="py-2 px-2">{{ g.sucursal }}</TableCell>
                                    <TableCell class="py-2 px-2">
                                        <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-200 text-xs border border-rose-500/30">
                                            {{ g.tipo }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="py-2 px-2 text-muted-foreground italic">{{ g.descripcion }}</TableCell>
                                    <TableCell class="py-2 px-2 font-medium">{{ formatCurrency(g.total) }}</TableCell>
                                </TableRow>
                                <TableRow v-if="!gastosPorTipo.length">
                                    <TableCell colspan="5" class="text-center py-4 text-muted-foreground">No hay gastos registrados</TableCell>
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
                                <td class="py-2">{{ money(x.neto_final) }}</td>
                                <td class="py-2">{{ money(x.neto_inicial) }}</td>
                                <td class="py-2">{{ money(x.creditos) }}</td>
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
