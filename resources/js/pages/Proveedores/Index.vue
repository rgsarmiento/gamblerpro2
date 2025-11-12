<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { toast } from 'vue-sonner'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/components/ui/table'
import {
  Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue
} from '@/components/ui/select'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter
} from '@/components/ui/dialog'

/* ======================
   Tipos e interfaces
====================== */
interface LinkItem { url: string | null; label: string; active: boolean }
interface Paginator<T> { data: T[]; links: LinkItem[]; total: number }
interface Casino { id: number; nombre: string }
interface Sucursal { id: number; nombre: string; casino_id: number }
interface Proveedor { id: number; nombre: string; identificacion: string; telefono?: string; direccion?: string; activo: number; sucursal_id: number; sucursal?: { nombre: string } }
interface Filters { search?: string; casino_id?: number | null; sucursal_id?: number | null }
interface User { id: number; roles: string[]; casino_id?: number | null; sucursal_id?: number | null }

/* ======================
   Props desde Laravel
====================== */
const props = withDefaults(
  defineProps<{
    proveedores: Paginator<Proveedor>
    sucursales: Sucursal[]
    casinos: Casino[]
    filters?: Filters
    user: User
  }>(),
  { filters: () => ({ search: '', casino_id: null, sucursal_id: null }) }
)

/* ======================
   Filtros
====================== */
const search = ref(props.filters.search ?? '')
const selectedCasino = ref<number | null>(props.filters.casino_id ?? null)
const selectedSucursal = ref<number | null>(props.filters.sucursal_id ?? null)
const role = computed(() => props.user.roles[0] ?? '')

const sucursalesFiltradas = computed(() => {
  if (!selectedCasino.value) return []
  return props.sucursales.filter(s => s.casino_id === selectedCasino.value)
})

watch([search, selectedCasino, selectedSucursal], ([s, c, su]) => {
  if (role.value === 'master_admin' || role.value === 'casino_admin') {
    if (su) {
      router.get('/proveedores', { search: s, casino_id: c, sucursal_id: su }, { preserveState: true, replace: true })
    }
  } else {
    router.get('/proveedores', { search: s }, { preserveState: true, replace: true })
  }
})

/* ======================
   Modal y formulario
====================== */
const showModal = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)

const form = useForm({
  nombre: '',
  identificacion: '',
  telefono: '',
  direccion: '',
  sucursal_id: '',
})

const openModal = (p?: Proveedor) => {
  isEditing.value = !!p
  showModal.value = true
  if (p) {
    editingId.value = p.id
    form.nombre = p.nombre
    form.identificacion = p.identificacion
    form.telefono = p.telefono ?? ''
    form.direccion = p.direccion ?? ''
    form.sucursal_id = String(p.sucursal_id)
  } else {
    form.reset()
  }
}

const closeModal = () => {
  showModal.value = false
  isEditing.value = false
  editingId.value = null
  form.reset()
}

const save = () => {
  if (isEditing.value && editingId.value) {
    form.put(`/proveedores/${editingId.value}`, {
      onSuccess: () => {
        toast.success('Proveedor actualizado correctamente')
        closeModal()
      },
      onError: (e) => toast.error(e.identificacion ?? 'Error al actualizar proveedor'),
    })
  } else {
    form.post('/proveedores', {
      onSuccess: () => {
        toast.success('Proveedor creado correctamente')
        closeModal()
      },
      onError: (e) => toast.error(e.identificacion ?? 'Error al crear proveedor'),
    })
  }
}

/* ======================
   Eliminar y estado
====================== */
const deleteProveedor = (id: number) => {
  if (confirm('¿Eliminar este proveedor?')) {
    router.delete(`/proveedores/${id}`, {
      onSuccess: () => toast.success('Proveedor eliminado correctamente'),
      onError: (e) => toast.error(e.proveedor ?? 'No se pudo eliminar'),
    })
  }
}

const toggleActivo = (p: Proveedor, nuevoEstado: boolean) => {
  const anterior = p.activo
  p.activo = nuevoEstado ? 1 : 0

  router.patch(`/proveedores/${p.id}/toggle`, { activo: p.activo }, {
    preserveScroll: true,
    onSuccess: () => toast.success('Estado actualizado'),
    onError: () => {
      p.activo = anterior
      toast.error('Error al actualizar estado')
    },
  })
}

/* ======================
   Rol con sucursal fija
====================== */
if (props.user.roles.includes('sucursal_admin') && props.user.sucursal_id) {
  form.sucursal_id = String(props.user.sucursal_id)
}
</script>

<template>
  <Head title="Proveedores" />

  <AppLayout>
    <div class="p-4 flex flex-col gap-4">
      <!-- Encabezado -->
      <div class="flex justify-between items-center">
        <h1 class="text-xl font-bold">📦 Proveedores</h1>
        <Button @click="openModal()" class="bg-indigo-600 text-white">Nuevo proveedor</Button>
      </div>

      <!-- Filtros -->
      <div v-if="role === 'master_admin' || role === 'casino_admin'" class="bg-card p-4 rounded-lg border grid grid-cols-4 gap-4">
        <div v-if="role === 'master_admin'">
          <label class="text-sm font-medium">Casino</label>
          <Select v-model="selectedCasino">
            <SelectTrigger><SelectValue placeholder="Seleccione..." /></SelectTrigger>
            <SelectContent>
              <SelectGroup>
                <SelectLabel>Casinos</SelectLabel>
                <SelectItem v-for="c in props.casinos" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <div>
          <label class="text-sm font-medium">Sucursal</label>
          <Select v-model="selectedSucursal">
            <SelectTrigger><SelectValue placeholder="Seleccione..." /></SelectTrigger>
            <SelectContent>
              <SelectGroup>
                <SelectLabel>Sucursales</SelectLabel>
                <SelectItem v-for="s in (role === 'master_admin' ? sucursalesFiltradas : props.sucursales)" :key="s.id" :value="s.id">
                  {{ s.nombre }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>
      </div>

      <!-- Tabla -->
      <div class="bg-card rounded-lg shadow border border-border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nombre</TableHead>
              <TableHead>Identificación</TableHead>
              <TableHead>Teléfono</TableHead>
              <TableHead>Dirección</TableHead>
              <TableHead>Sucursal</TableHead>
              <TableHead>Estado</TableHead>
              <TableHead>Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="p in props.proveedores.data" :key="p.id">
              <TableCell>{{ p.nombre }}</TableCell>
              <TableCell>{{ p.identificacion }}</TableCell>
              <TableCell>{{ p.telefono }}</TableCell>
              <TableCell>{{ p.direccion }}</TableCell>
              <TableCell>{{ p.sucursal?.nombre }}</TableCell>
              <TableCell>
                <div class="flex items-center gap-2">
                  <Switch :model-value="Boolean(p.activo)" @update:model-value="(val) => toggleActivo(p, val)" />
                  <Badge :variant="p.activo ? 'default' : 'secondary'">{{ p.activo ? 'Activo' : 'Inactivo' }}</Badge>
                </div>
              </TableCell>
              <TableCell class="flex gap-2">
                <Button variant="outline" size="sm" @click="openModal(p)">Editar</Button>
                <Button variant="destructive" size="sm" @click="deleteProveedor(p.id)">Eliminar</Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Paginación -->
      <div class="flex gap-2 mt-4 justify-end">
        <a v-for="link in props.proveedores.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'"
          :class="[
            'px-3 py-1 rounded text-sm',
            link.active
              ? 'bg-primary text-primary-foreground'
              : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
          ]" />
      </div>

      <!-- Modal -->
      <Dialog v-model:open="showModal">
        <DialogContent class="sm:max-w-[600px]">
          <DialogHeader>
            <DialogTitle>{{ isEditing ? 'Editar proveedor' : 'Nuevo proveedor' }}</DialogTitle>
            <DialogDescription>Complete los datos del proveedor.</DialogDescription>
          </DialogHeader>

          <form @submit.prevent="save" class="grid grid-cols-2 gap-4 mt-4">
            <Input v-model="form.nombre" placeholder="Nombre del proveedor" />
            <Input v-model="form.identificacion" placeholder="Identificación" />
            <Input v-model="form.telefono" placeholder="Teléfono" />
            <Input v-model="form.direccion" placeholder="Dirección" />
            <Button type="submit" class="col-span-2 bg-indigo-600 text-white">
              {{ isEditing ? 'Actualizar' : 'Guardar' }}
            </Button>
          </form>

          <DialogFooter>
            <Button variant="outline" @click="closeModal">Cancelar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
