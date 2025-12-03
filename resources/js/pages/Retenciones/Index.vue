<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watchEffect, nextTick } from 'vue'
import { toast } from 'vue-sonner'
import { usePage } from '@inertiajs/vue3'

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
    AlertDialog,
    AlertDialogTrigger,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogCancel,
    AlertDialogAction
} from '@/components/ui/alert-dialog'
import { Trash2, Pencil } from "lucide-vue-next"

interface Retencion {
    id: number
    fecha: string
    cedula: string
    nombre: string
    valor_premio: number
    valor_retencion: number
    observacion?: string
    sucursal?: { nombre: string }
    usuario?: { name: string }
}

const props = defineProps<{
    retenciones: any
    totalRegistros: number
    totalPremios: number
    totalRetenciones: number
    casinos: Array<{ id: number, nombre: string }>
    sucursales: Array<{ id: number, nombre: string, casino_id: number }>
    filters: { fecha: string, casino_id?: number, sucursal_id?: number }
    ultimaFechaConfirmada: string | null
    user: { id: number, name: string, roles: string[], sucursal_id?: number, casino_id?: number }
}>()

const form = useForm({
    id: null as number | null,
    fecha: props.filters.fecha || new Date().toISOString().split('T')[0],
    sucursal_id: props.user.sucursal_id || null as number | null,
    cedula: '',
    nombre: '',
    valor_premio: '' as string | number,
    observacion: '',
})

const filterForm = useForm({
    fecha: props.filters.fecha || new Date().toISOString().split('T')[0],
    casino_id: props.filters.casino_id || null as number | null,
    sucursal_id: props.filters.sucursal_id || null as number | null,
})

const showModal = ref(false)
const isEditing = ref(false)
const role = computed(() => props.user.roles[0] ?? '')

const sucursalesFiltradas = computed(() => {
    if (role.value === 'master_admin' && filterForm.casino_id) {
        return props.sucursales.filter(s => s.casino_id == filterForm.casino_id)
    }
    return props.sucursales
})

const valorRetencion = computed(() => {
    const valorStr = String(form.valor_premio || 0)
    const premio = parseFloat(valorStr) || 0
    return premio * 0.20
})

const valorNeto = computed(() => {
    const valorStr = String(form.valor_premio || 0)
    const premio = parseFloat(valorStr) || 0
    const retencion = valorRetencion.value
    return premio - retencion
})

const openModal = async (retencion?: Retencion) => {
    if (retencion) {
        isEditing.value = true
        form.id = retencion.id
        form.fecha = retencion.fecha.split('T')[0]
        form.cedula = retencion.cedula
        form.nombre = retencion.nombre
        form.observacion = retencion.observacion || ''
        
        await nextTick()
        form.valor_premio = Number(retencion.valor_premio)
    } else {
        isEditing.value = false
        form.reset()
        form.fecha = filterForm.fecha
        if (props.user.sucursal_id) {
            form.sucursal_id = props.user.sucursal_id
        }
    }
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    form.reset()
}

const save = () => {
    if (isEditing.value && form.id) {
        form.put(`/retenciones/${form.id}`, {
            onSuccess: () => {
                toast.success('Retención actualizada correctamente')
                closeModal()
            },
            onError: () => toast.error('Error al actualizar retención'),
        })
    } else {
        form.post('/retenciones', {
            onSuccess: () => {
                toast.success('Retención registrada correctamente')
                closeModal()
            },
            onError: () => toast.error('Error al registrar retención'),
        })
    }
}

const deleteRetencion = (id: number) => {
    if (confirm('¿Eliminar esta retención?')) {
        router.delete(`/retenciones/${id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Retención eliminada correctamente'),
            onError: () => toast.error('Error al eliminar retención'),
        })
    }
}

const applyFilters = () => {
    router.get('/retenciones', {
        fecha: filterForm.fecha,
        casino_id: filterForm.casino_id,
        sucursal_id: filterForm.sucursal_id,
    }, { preserveState: true })
}

const canEdit = (retencionFecha: string) => {
    if (role.value === 'master_admin' || role.value === 'casino_admin') return true
    if (!props.ultimaFechaConfirmada) return true
    return retencionFecha > props.ultimaFechaConfirmada
}

const formatDate = (dateString: string) => {
    if (!dateString) return ''
    const parts = dateString.split('T')[0].split('-')
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`
    }
    return dateString
}

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(value)
}

const page = usePage()
watchEffect(() => {
    const errors = page.props.errors || {}
    Object.entries(errors).forEach(([field, message]) => {
        if (message) {
            toast.error('Error', { description: String(message) })
        }
    })
})
</script>

<template>
    <Head title="Retenciones" />
    
    <AppLayout>
        <div class="p-4 space-y-4">
            <!-- Encabezado -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">💰 Retenciones</h1>
                <Button @click="openModal()" class="bg-indigo-600 text-white">Nueva Retención</Button>
            </div>

            <!-- Filtros -->
            <div class="bg-card p-4 rounded-lg border space-y-4">
                <div class="grid grid-cols-3 md:grid-cols-4 gap-4">
                    <div>
                        <label class="text-sm font-medium">Fecha</label>
                        <Input type="date" v-model="filterForm.fecha" @change="applyFilters" />
                    </div>
                    
                    <div v-if="role === 'master_admin'">
                        <label class="text-sm font-medium">Casino</label>
                        <Select v-model="filterForm.casino_id" @update:model-value="applyFilters" class=" w-full">
                            <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Todos</SelectItem>
                                <SelectItem v-for="c in casinos" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="role === 'master_admin' || role === 'casino_admin'">
                        <label class="text-sm font-medium">Sucursal</label>
                        <Select v-model="filterForm.sucursal_id" @update:model-value="applyFilters" class=" w-full">
                            <SelectTrigger><SelectValue placeholder="Todas" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Todas</SelectItem>
                                <SelectItem v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">{{ s.nombre }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Totales -->
                <div class="grid grid-cols-4 gap-4 pt-4 border-t">
                    <div class="bg-blue-50 dark:bg-blue-950 p-3 rounded">
                        <p class="text-sm text-muted-foreground">Total Registros</p>
                        <p class="text-2xl font-bold">{{ totalRegistros }}</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-950 p-3 rounded">
                        <p class="text-sm text-muted-foreground">Total Premios</p>
                        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(totalPremios) }}</p>
                    </div>
                    <div class="bg-orange-50 dark:bg-orange-950 p-3 rounded">
                        <p class="text-sm text-muted-foreground">Total Retenciones</p>
                        <p class="text-2xl font-bold text-orange-600">{{ formatCurrency(totalRetenciones) }}</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-950 p-3 rounded">
                        <p class="text-sm text-muted-foreground">Neto a Pagar</p>
                        <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(totalPremios - totalRetenciones) }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-card rounded-lg border overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Fecha</TableHead>
                            <TableHead v-if="role !== 'cajero' && role !== 'sucursal_admin'">Sucursal</TableHead>
                            <TableHead>Cédula</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Valor Premio</TableHead>
                            <TableHead>Retención (20%)</TableHead>
                            <TableHead>Neto</TableHead>
                            <TableHead>Observación</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in retenciones.data" :key="r.id">
                            <TableCell>{{ formatDate(r.fecha) }}</TableCell>
                            <TableCell v-if="role !== 'cajero' && role !== 'sucursal_admin'">{{ r.sucursal?.nombre }}</TableCell>
                            <TableCell>{{ r.cedula }}</TableCell>
                            <TableCell>{{ r.nombre }}</TableCell>
                            <TableCell class="text-green-600 font-semibold">{{ formatCurrency(r.valor_premio) }}</TableCell>
                            <TableCell class="text-orange-600 font-semibold">{{ formatCurrency(r.valor_retencion) }}</TableCell>
                            <TableCell class="text-purple-600 font-semibold">{{ formatCurrency(r.valor_premio - r.valor_retencion) }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">{{ r.observacion || '-' }}</TableCell>
                            <TableCell>
                                <div class="flex gap-2">
                                    <Button 
                                        v-if="canEdit(r.fecha)"
                                        variant="outline" 
                                        size="sm"
                                        @click="openModal(r)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        v-else 
                                        variant="outline" 
                                        size="sm" 
                                        disabled 
                                        class="opacity-50 cursor-not-allowed"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>

                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button 
                                                variant="destructive" 
                                                size="sm" 
                                                :disabled="!canEdit(r.fecha)" 
                                                :class="{'opacity-50 cursor-not-allowed': !canEdit(r.fecha)}"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </AlertDialogTrigger>

                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>¿Eliminar Retención?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    ¿Está seguro de eliminar la retención de {{ r.nombre }} ({{ r.cedula }})?
                                                    <br><br>
                                                    <strong>Premio:</strong> {{ formatCurrency(r.valor_premio) }}<br>
                                                    <strong>Retención:</strong> {{ formatCurrency(r.valor_retencion) }}
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                <AlertDialogAction @click="router.delete(`/retenciones/${r.id}`, {
                                                    preserveScroll: true,
                                                    onSuccess: () => toast.success('Retención eliminada correctamente'),
                                                    onError: () => toast.error('Error al eliminar retención'),
                                                })">
                                                    Eliminar
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Paginación -->
            <div class="flex gap-2 justify-end">
                <a v-for="link in retenciones.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent'
                    ]" />
            </div>

            <!-- Modal -->
            <Dialog v-model:open="showModal">
                <DialogContent class="sm:max-w-[600px]">
                    <DialogHeader>
                        <DialogTitle>{{ isEditing ? 'Editar Retención' : 'Nueva Retención' }}</DialogTitle>
                        <DialogDescription>Complete los datos de la retención. El valor de retención se calcula automáticamente (20%).</DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="save" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium">Fecha *</label>
                                <Input type="date" v-model="form.fecha" required />
                            </div>

                            <div v-if="role === 'master_admin' || role === 'casino_admin'">
                                <label class="text-sm font-medium">Sucursal *</label>
                                <Select v-model="form.sucursal_id" required>
                                    <SelectTrigger><SelectValue placeholder="Seleccione" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">{{ s.nombre }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label class="text-sm font-medium">Cédula *</label>
                                <Input v-model="form.cedula" placeholder="Número de cédula" required />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Nombre *</label>
                                <Input v-model="form.nombre" placeholder="Nombre completo" required />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Valor Premio *</label>
                                <Input type="number" v-model="form.valor_premio" placeholder="0" step="0.01" required />
                            </div>

                            <div>
                                <label class="text-sm font-medium">Retención (20%)</label>
                                <Input :value="formatCurrency(valorRetencion)" disabled class="bg-muted" />
                            </div>

                            <div class="col-span-2">
                                <label class="text-sm font-medium">Neto a Pagar</label>
                                <Input :value="formatCurrency(valorNeto)" disabled class="bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 font-semibold" />
                            </div>

                            <div class="col-span-2">
                                <label class="text-sm font-medium">Observación</label>
                                <Input v-model="form.observacion" placeholder="Observaciones opcionales" />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeModal">Cancelar</Button>
                            <Button type="submit" class="bg-indigo-600 text-white">
                                {{ isEditing ? 'Actualizar' : 'Guardar' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
