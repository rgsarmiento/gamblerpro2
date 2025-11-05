<!-- <script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { toast } from 'vue-sonner'

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

const props = defineProps<{
  cierres: any
  casinos: Casino[]
  sucursales: Sucursal[]
  user: User
}>()

//const selectedCasino = ref<number | null>(props.user.roles.includes('master_admin') ? null : props.user.casino_id ?? null)
//const selectedSucursal = ref<number | null>(props.user.roles.includes('sucursal_admin') || props.user.roles.includes('cajero') ? props.user.sucursal_id : null)

const sucursalesFiltradas = computed(() => {
  if (!selectedCasino.value) return props.sucursales
  return props.sucursales.filter((s: any) => s.casino_id === selectedCasino.value)
})

const hacerCierre = () => {
  router.post('/cierres', {
    casino_id: selectedCasino.value,
    sucursal_id: selectedSucursal.value,
  }, {
    onSuccess: () => {
      toast.success('Cierre de caja realizado correctamente')
    },
    onError: () => toast.error('Error al realizar el cierre')
  })
}
</script>

<template>
  <Head title="Cierres de Caja" />
  <AppLayout>
    <div class="space-y-6">
      <div class="flex items-end gap-4">
        <div v-if="props.user.roles.includes('master_admin')" class="flex flex-col">
          <label>Casino</label>
          <select v-model="selectedCasino" class="border rounded px-2 py-1 w-48">
            <option :value="null">Seleccione casino</option>
            <option v-for="c in props.casinos" :key="c.id" :value="c.id">{{ c.nombre }}</option>
          </select>
        </div>

        <div v-if="!props.user.roles.includes('sucursal_admin') && !props.user.roles.includes('cajero')" class="flex flex-col">
          <label>Sucursal</label>
          <select v-model="selectedSucursal" class="border rounded px-2 py-1 w-48">
            <option :value="null">Seleccione sucursal</option>
            <option v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">{{ s.nombre }}</option>
          </select>
        </div>

        <Button class="bg-indigo-600 text-white" @click="hacerCierre">Cerrar Caja</Button>
      </div>

      <div class="bg-card rounded-lg shadow border border-border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>ID</TableHead>
              <TableHead>Casino</TableHead>
              <TableHead>Sucursal</TableHead>
              <TableHead>Total Recaudo</TableHead>
              <TableHead>Total Gastos</TableHead>
              <TableHead>Total Cierre</TableHead>
              <TableHead>Fecha</TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            <TableRow v-for="c in props.cierres.data" :key="c.id">
              <TableCell>{{ c.id }}</TableCell>
              <TableCell>{{ c.casino?.nombre ?? '—' }}</TableCell>
              <TableCell>{{ c.sucursal?.nombre }}</TableCell>
              <TableCell>{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(c.total_recaudo) }}</TableCell>
              <TableCell>{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(c.total_gastos) }}</TableCell>
              <TableCell>{{ new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(c.total_cierre) }}</TableCell>
              <TableCell>{{ new Date(c.created_at).toLocaleString() }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>
  </AppLayout>
</template> -->
