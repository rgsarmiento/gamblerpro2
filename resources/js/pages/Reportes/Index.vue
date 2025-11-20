<script setup lang="ts">
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

import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'

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
    gastosPorTipo: Row[],
    tablaPrincipal: Row[],
    tablaSecundaria: Row[],
    chart: { labels: string[], data: number[], title: string },

    casinos: { id: number, nombre: string }[],
    sucursales: { id: number, nombre: string, casino_id: number }[],
    maquinas: { id: number, ndi: string, nombre: string, sucursal_id: number }[],
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

// exportar tabla utilitario
const exportar = (headings: string[], rows: any[], nombre = 'reporte.xlsx') => {
    const form = document.createElement('form')
    form.method = 'GET'
    form.action = '/reportes/export'
    const add = (k: string, v: any) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = JSON.stringify(v); form.appendChild(i) }
    add('headings', headings)
    add('rows', rows)
    const n = document.createElement('input'); n.type = 'hidden'; n.name = 'name'; n.value = nombre; form.appendChild(n)
    document.body.appendChild(form)
    form.submit()
    form.remove()
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



const exportTablaPrincipal = () => {
    let h: string[] = []; let r: any[] = []
    if (form.value.mode === 'all_casinos') {
        h = ['Casino', 'Neto Final', 'Neto Inicial', 'Créditos', 'Recaudo']
        r = props.tablaPrincipal.map(x => [x.casino, x.neto_final, x.neto_inicial, x.creditos, x.recaudo])
    } else if (form.value.mode === 'casino') {
        h = ['Sucursal', 'Neto Final', 'Neto Inicial', 'Créditos', 'Recaudo']
        r = props.tablaPrincipal.map(x => [x.sucursal, x.neto_final, x.neto_inicial, x.creditos, x.recaudo])
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


const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(value);
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

                <!-- <label class="text-sm font-semibold text-slate-300">Máquina</label>
                    <select v-model="form.maquina_id" @change="actualizar" class="mt-1 w-full px-3 py-2 rounded border border-slate-600 
                   bg-slate-800 text-slate-100 focus:border-indigo-400">
                        <option value="">Todas</option>
                        <option v-for="m in maquinasFiltradas" :key="m.id" :value="m.id">
                            {{ m.ndi }} - {{ m.nombre }}
                        </option>
                    </select> -->


                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-slate-300">Maquinas</label>
                        <Combobox v-model="form.maquina_id" by="id" class="w-full">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child class="border w-full">
                                    <Button variant="outline" class="justify-between w-full">
                                        <template v-if="maquinaSeleccionada">
                                            {{ maquinaSeleccionada.ndi }} - {{ maquinaSeleccionada.nombre }}
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
                                        {{ m.ndi }} - {{ m.nombre }}
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
                    <!-- all_casinos / casino -->
                    <table v-if="form.mode === 'all_casinos' || form.mode === 'casino'"
                        class="min-w-[720px] w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">{{ form.mode === 'all_casinos' ? 'Casino' : 'Sucursal' }}</th>
                                <th class="py-2">Neto Final</th>
                                <th class="py-2">Neto Inicial</th>
                                <th class="py-2">Créditos</th>
                                <th class="py-2">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="(x.casino || x.sucursal)" class="border-b">
                                <td class="py-2">{{ x.casino || x.sucursal }}</td>
                                <td class="py-2">{{ money(x.neto_final) }}</td>
                                <td class="py-2">{{ money(x.neto_inicial) }}</td>
                                <td class="py-2">{{ money(x.creditos) }}</td>
                                <td class="py-2" :class="rojoSiNegativo(x.recaudo)">{{ money(x.recaudo) }}</td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="5" class="py-2 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- sucursal -->
                    <table v-else-if="form.mode === 'sucursal'" class="min-w-[900px] w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">Máquina</th>
                                <th class="py-2">Entrada</th>
                                <th class="py-2">Salida</th>
                                <th class="py-2">Jackpots</th>
                                <th class="py-2">Neto Final</th>
                                <th class="py-2">Neto Inicial</th>
                                <th class="py-2">Créditos</th>
                                <th class="py-2">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="x.maquina" class="border-b">
                                <td class="py-2">{{ x.maquina }}</td>
                                <td class="py-2">{{ money(x.entrada) }}</td>
                                <td class="py-2">{{ money(x.salida) }}</td>
                                <td class="py-2">{{ money(x.jackpots) }}</td>
                                <td class="py-2">{{ money(x.neto_final) }}</td>
                                <td class="py-2">{{ money(x.neto_inicial) }}</td>
                                <td class="py-2">{{ money(x.creditos) }}</td>
                                <td class="py-2" :class="rojoSiNegativo(x.recaudo)">{{ money(x.recaudo) }}</td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="8" class="py-2 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- maquina -->
                    <table v-else class="min-w-[1000px] w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">Fecha</th>
                                <th class="py-2">Máquina</th>
                                <th class="py-2">Entrada</th>
                                <th class="py-2">Salida</th>
                                <th class="py-2">Jackpots</th>
                                <th class="py-2">Neto Final</th>
                                <th class="py-2">Neto Inicial</th>
                                <th class="py-2">Créditos</th>
                                <th class="py-2">Recaudo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in props.tablaPrincipal" :key="`${x.fecha}-${x.maquina_id}`" class="border-b">
                                <td class="py-2">{{ x.fecha }}</td>
                                <td class="py-2">{{ x.maquina?.ndi }} - {{ x.maquina?.nombre }}</td>
                                <td class="py-2">{{ money(x.entrada) }}</td>
                                <td class="py-2">{{ money(x.salida) }}</td>
                                <td class="py-2">{{ money(x.jackpots) }}</td>
                                <td class="py-2">{{ money(x.neto_final) }}</td>
                                <td class="py-2">{{ money(x.neto_inicial) }}</td>
                                <td class="py-2">{{ money(x.total_creditos) }}</td>
                                <td class="py-2" :class="rojoSiNegativo(x.total_recaudo)">{{ money(x.total_recaudo) }}
                                </td>
                            </tr>
                            <tr v-if="!props.tablaPrincipal.length">
                                <td colspan="9" class="py-2 text-center text-muted-foreground">Sin datos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            

            <!-- MODO AGRUPADO -->
            <div v-if="['all_casinos', 'casino'].includes(mode)" class="p-4 rounded-lg shadow border bg-gradient-to-br from-rose-600/30 to-rose-900/20 border-rose-500/40">
                
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold">Gastos Agrupados</h2>
                    <button @click="exportGastos" class="text-sm px-3 py-1 rounded border">Exportar</button>
                </div>
                <div class="bg-card rounded-lg shadow border">
                    <Table class="min-w-[520px] w-full text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Sucursal</TableHead>
                                <TableHead>Total Gastos</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="g in gastosPorTipo" :key="g.sucursal">
                                <TableCell>{{ g.sucursal }}</TableCell>
                                <TableCell>{{ money(g.total) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- MODO DETALLADO -->
            <div v-else class="p-4 rounded-lg shadow border bg-gradient-to-br from-rose-600/30 to-rose-900/20 border-rose-500/40">
               
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold">Gastos Detallados</h2>
                    <button @click="exportGastos" class="text-sm px-3 py-1 rounded border">Exportar</button>
                </div>
                <div class="bg-card rounded-lg shadow border">
                    <Table class="min-w-[520px] w-full text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Sucursal</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Total</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="g in gastosPorTipo" :key="g.id">
                                <TableCell>{{ g.fecha }}</TableCell>
                                <TableCell>{{ g.sucursal }}</TableCell>
                                <TableCell>{{ g.tipo }}</TableCell>
                                <TableCell>{{ g.descripcion }}</TableCell>
                                <TableCell>{{ formatCurrency(g.total) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
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
