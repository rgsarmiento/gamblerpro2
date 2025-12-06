<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { toast } from 'vue-sonner'


// 🎨 Charts
import { Line, Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement
} from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, LineElement, BarElement, CategoryScale, LinearScale, PointElement)

// 🧾 Tipado de Props
interface Totales {
  lecturas: number
  gastos: number
  saldo: number
}

interface LecturaDia {
  dia: string
  total: number
}

interface GastoDia {
  dia: string
  total: number
}

interface SucursalData {
  sucursal: string
  total: number
}

interface Casino {
  id: number
  nombre: string
}

interface Sucursal {
  id: number
  nombre: string
  casino_id: number
}

interface User {
  id: number
  name: string
  roles: string[]
  casino_id?: number
  sucursal_id?: number
}

// ✅ Props definidos con tipo seguro
const props = defineProps<{
  totales: Totales
  lecturasPorDia: LecturaDia[]
  gastosPorDia: GastoDia[]
  recaudoPorSucursal: SucursalData[]
  casinos: Casino[]
  sucursales: Sucursal[]
  user: User
  range: string
  inicio: string
  fin: string
}>()

// 🧠 Rol actual
const role = computed(() => props.user.roles[0] ?? '')


// 📋 Formulario de filtros
const form = ref({
  casino_id: props.user?.casino_id ?? '',
  sucursal_id: props.user?.sucursal_id ?? '',
  range: props.range ?? 'this_month',
  start_date: props.inicio ?? '',
  end_date: props.fin ?? '',
})

// 🔍 Filtrar sucursales por casino o rol
const sucursalesFiltradas = computed(() => {
  if (role.value === 'master_admin' && form.value.casino_id) {
    return props.sucursales.filter(s => s.casino_id === Number(form.value.casino_id))
  }
  if (role.value === 'casino_admin') {
    return props.sucursales.filter(s => s.casino_id === props.user.casino_id)
  }
  if (role.value === 'sucursal_admin' || role.value === 'cajero') {
    return props.sucursales.filter(s => s.id === props.user.sucursal_id)
  }
  return props.sucursales
})

// 📤 Recargar datos al cambiar filtro
const actualizarFiltros = () => {
  if (form.value.range !== 'custom') {
    router.get('/dashboard', form.value, { preserveScroll: true, preserveState: true })
  }
}

// Nuevo método para aplicar el rango personalizado
const aplicarRangoPersonalizado = () => {
  if (!form.value.start_date || !form.value.end_date) {
    toast.error('Selecciona ambas fechas para aplicar el rango.')
    return
  }

  if (form.value.start_date > form.value.end_date) {
    toast.error('La fecha inicial no puede ser mayor que la final.')
    return
  }

  router.get(
    '/dashboard',
    {
      range: 'custom',
      start_date: form.value.start_date,
      end_date: form.value.end_date,
    },
    { preserveState: true, replace: true }
  )
}

// === Configuración de gráficos ===
const lecturasChart = computed(() => ({
  labels: props.lecturasPorDia?.map(l => l.dia) ?? [],
  datasets: [
    {
      label: 'Recaudo',
      data: props.lecturasPorDia?.map(l => l.total) ?? [],
      borderColor: '#22c55e',
      backgroundColor: 'rgba(34,197,94,0.3)',
      tension: 0.3,
    },
  ],
}))

const gastosChart = computed(() => ({
  labels: props.gastosPorDia?.map(g => g.dia) ?? [],
  datasets: [
    {
      label: 'Gastos',
      data: props.gastosPorDia?.map(g => g.total) ?? [],
      backgroundColor: 'rgba(239,68,68,0.6)',
    },
  ],
}))

const sucursalChart = computed(() => ({
  labels: props.recaudoPorSucursal?.map(s => s.sucursal) ?? [],
  datasets: [
    {
      label: 'Recaudo por sucursal',
      data: props.recaudoPorSucursal?.map(s => s.total) ?? [],
      backgroundColor: 'rgba(59,130,246,0.7)',
      borderRadius: 6,
    },
  ],
}))

const sucursalOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: "x",
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true },
  },
  scales: {
    x: {
      ticks: {
        color: '#aaa',
        maxRotation: 60, // 🔹 Inclina las etiquetas
        minRotation: 60,
        autoSkip: false, // 🔹 No omite etiquetas largas
        font: { size: 10 },
      },
      grid: {
        color: '#333',
        drawBorder: false,
      },
    },
    y: {
      ticks: {
        color: '#aaa',
        callback: (value: number) =>
          new Intl.NumberFormat('es-CO').format(value),
      },
      grid: {
        color: '#333',
        drawBorder: false,
      },
    },
  },
}


const chartOptions = {
  responsive: true,
  maintainAspectRatio: false, // 👈 esto es lo que evita el desbordamiento
  plugins: {
    legend: { display: true, labels: { color: '#aaa' } },
    title: { display: false },
  },
  scales: {
    x: { ticks: { color: '#aaa' }, grid: { color: '#333' } },
    y: { ticks: { color: '#aaa' }, grid: { color: '#333' } },
  },
}

</script>

<template>

  <Head title="Dashboard" />
  <AppLayout>
    <div class="p-6 space-y-8">

      <h1 class="text-2xl font-bold text-foreground">📊 Panel de Control {{ role }}</h1>

      <div class="grid gap-4 backdrop-blur-md md:grid-cols-2">
        <div v-if="role === 'master_admin'" class="flex flex-col">
          <label class="text-sm font-medium mb-1">Casino</label>
          <select v-model="form.casino_id" @change="actualizarFiltros"
            class="border rounded px-2 py-1 bg-background text-foreground">
            <option value="">Todos</option>
            <option v-for="c in props.casinos" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </div>

        <div v-if="role === 'master_admin' || role === 'casino_admin'" class="flex flex-col">
          <label class="text-sm font-medium mb-1">Sucursal</label>
          <select v-model="form.sucursal_id" @change="actualizarFiltros"
            class="border rounded px-2 py-1 bg-background text-foreground">
            <option value="">Todas</option>
            <option v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">{{ s.nombre }}</option>
          </select>
        </div>
      </div>

      <div
        class="flex flex-wrap items-end gap-4 bg-card/80 backdrop-blur-md p-6 rounded-xl shadow-md border border-border/60">

        <!-- Select de rango -->
        <div class="flex flex-col min-w-[220px]">
          <label class="text-sm font-medium mb-1 text-muted-foreground">Rango de fechas</label>
          <select v-model="form.range" @change="actualizarFiltros"
            class="border rounded-lg px-3 py-2 bg-background text-foreground focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
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

        <!-- Fechas personalizadas -->
        <div v-if="form.range === 'custom'" class="flex items-end gap-4 animate-fade-in">
          <div>
            <label class="text-sm font-medium mb-1 text-muted-foreground">Desde</label>
            <input v-model="form.start_date" type="date"
              class="border rounded-lg px-3 py-2 bg-background text-foreground focus:ring-2 focus:ring-indigo-500 focus:outline-none transition" />
          </div>

          <div>
            <label class="text-sm font-medium mb-1 text-muted-foreground">Hasta</label>
            <input v-model="form.end_date" type="date"
              class="border rounded-lg px-3 py-2 bg-background text-foreground focus:ring-2 focus:ring-indigo-500 focus:outline-none transition" />
          </div>

          <button @click="aplicarRangoPersonalizado"
            class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-medium px-5 py-2.5 rounded-lg shadow hover:from-indigo-500 hover:to-purple-500 transition-all">
            Aplicar
          </button>
        </div>
      </div>





      <!-- 🔹 Totales -->
      <div class="grid gap-4 md:grid-cols-3">
        <div class="p-4 bg-card rounded shadow border">
          <p class="text-sm text-muted-foreground">💰 Total Recaudado</p>
          <p class="text-3xl font-bold text-green-600">
            {{
              new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                maximumFractionDigits: 0,
              }).format(props.totales?.lecturas ?? 0)
            }}
          </p>
        </div>
        <div class="p-4 bg-card rounded shadow border">
          <p class="text-sm text-muted-foreground">📉 Total Gastos</p>
          <p class="text-3xl font-bold text-red-600">
            {{
              new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                maximumFractionDigits: 0,
              }).format(props.totales?.gastos ?? 0)
            }}
          </p>
        </div>
        <div class="p-4 bg-card rounded shadow border">
          <p class="text-sm text-muted-foreground">📈 Saldo Neto</p>
          <p class="text-3xl font-bold" :class="(props.totales?.saldo ?? 0) >= 0 ? 'text-emerald-500' : 'text-red-500'">
            {{
              new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                maximumFractionDigits: 0,
              }).format(props.totales?.saldo ?? 0)
            }}
          </p>
        </div>
      </div>

      <!-- 🔹 Gráficas -->
      <div class="grid gap-6 md:grid-cols-2">
        <div class="p-4 bg-card rounded shadow border h-[400px] flex flex-col">
          <p class="font-semibold mb-2">📊 Recaudo diario</p>
          <div class="flex-1 relative">
            <Line :data="lecturasChart" :options="chartOptions" class="!w-full !h-full" />
          </div>
        </div>

        <div class="p-4 bg-card rounded shadow border h-[400px] flex flex-col">
          <p class="font-semibold mb-2">💸 Gastos diarios</p>
          <div class="flex-1 relative">
            <Bar :data="gastosChart" :options="chartOptions" class="!w-full !h-full" />
          </div>
        </div>

        <!-- <div class="p-4 bg-card rounded shadow border md:col-span-2 h-[450px] flex flex-col overflow-x-auto">
          <p class="font-semibold mb-2">🏢 Recaudo por sucursal</p>
          <div class="flex-1 relative min-w-[700px]">
            <Bar :data="sucursalChart" :options="sucursalOptions" class="!w-full !h-full" />
          </div>
        </div> -->

      </div>
    </div>
  </AppLayout>
</template>
