<script setup lang="ts">

import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableCaption } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

import { useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

import { Check, ChevronsUpDown, Search, Trash2, Pencil } from "lucide-vue-next"
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

import { toast } from 'vue-sonner'

import { usePage } from '@inertiajs/vue3'
import { watchEffect } from 'vue'
import { watch } from 'vue'

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

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog'

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Gastos',
        href: '/gastos',
    },
];

const form = useForm({
    casino_id: null as number | null,
    sucursal_id: null as number | null,
    tipo_gasto_id: null as number | null,
    proveedor_id: null as number | null,
    valor: '',
    descripcion: '',
    fecha: new Date().toISOString().split('T')[0],
})

const props = defineProps<{
    gastos: any
    total_registros: number
    total_gastos: number
    casinos: Array<{ id: number, nombre: string }>
    sucursales: Array<{ id: number, nombre: string, casino_id: number }>
    tipos_gasto: Array<{ id: number; nombre: string }>
    proveedores: Array<{ id: number; identificacion: string; nombre: string; sucursal_id: number }>
    ultimaFechaConfirmada: string | null
    user: { id: number, name: string, roles: string[], sucursal_id?: number, casino_id?: number }
}>()

if (props.user.roles.includes('cajero') || props.user.roles.includes('sucursal_admin')) {
    form.casino_id = props.user.casino_id ?? null
    form.sucursal_id = props.user.sucursal_id ?? null
}

const role = computed(() => props.user.roles[0] ?? '')

const sucursalesFiltradas = computed(() => {
    if (role.value === 'master_admin') {
        return props.sucursales.filter(s => s.casino_id == form.casino_id)
    }
    if (role.value === 'casino_admin') {
        return props.sucursales
    }
    return []
})

const ProveedoresFiltrados = computed(() => {
    if (role.value === 'master_admin' || role.value === 'casino_admin') {
        return props.proveedores.filter(m => m.sucursal_id == form.sucursal_id)
    }
    if (role.value === 'sucursal_admin' || role.value === 'cajero') {
        return props.proveedores.filter(m => m.sucursal_id == props.user.sucursal_id)
    }
    return []
})

const proveedorSeleccionado = computed(() =>
    props.proveedores.find(m => m.id == form.proveedor_id)
)

const deleteGasto = async (id: number) => {
    router.delete(`/gastos/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            router.visit('/gastos', { replace: true })
            toast.success('Eliminada correctamente', {
                description: 'El gasto fue eliminado y el total recalculado.',
            })
        },
        onError: (errors) => {
            console.error('Error al eliminar el gasto:', errors)
        },
    })
}

const formatCurrency = (value: any) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(value);
};

const page = usePage()

watchEffect(() => {
    const errors = page.props.errors || {}
    Object.entries(errors).forEach(([field, message]) => {
        if (message) {
            console.log(message);
            toast.error('Error al guardar lectura', {
                description: String(message),
            })
        }
    })
})

watch(
    [() => form.fecha, () => form.casino_id, () => form.sucursal_id],
    ([newFecha, newCasino, newSucursal]) => {
        if (
            (role.value === 'master_admin' && !newSucursal) ||
            (role.value === 'casino_admin' && !newSucursal)
        ) {
            return
        }

        router.get(
            '/gastos',
            {
                fecha: newFecha,
                casino_id: newCasino,
                sucursal_id: newSucursal,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            }
        )
    }
)

const editingGasto = ref<any>(null)
const editForm = useForm({
    tipo_gasto_id: null as number | null,
    proveedor_id: null as number | null,
    valor: '',
    descripcion: '',
    fecha: '',
})

// Búsqueda de proveedores en el modal
const proveedorSearch = ref('')

// Proveedores filtrados para el modal de edición
const ProveedoresFiltradosModal = computed(() => {
    const filtered = ProveedoresFiltrados.value
    if (!proveedorSearch.value) return filtered
    
    const search = proveedorSearch.value.toLowerCase()
    return filtered.filter(p => 
        p.identificacion.toLowerCase().includes(search) || 
        p.nombre.toLowerCase().includes(search)
    )
})

const openEditModal = (gasto: any) => {
    editingGasto.value = gasto
    editForm.tipo_gasto_id = gasto.tipo_gasto_id
    editForm.proveedor_id = gasto.proveedor_id
    editForm.valor = gasto.valor
    editForm.descripcion = gasto.descripcion
    editForm.fecha = gasto.fecha
    proveedorSearch.value = '' // Limpiar búsqueda al abrir
}

const updateGasto = () => {
    if (!editingGasto.value) return

    editForm.put(`/gastos/${editingGasto.value.id}`, {
        onSuccess: () => {
            editingGasto.value = null
            toast.success('Gasto actualizado correctamente')
        },
        onError: () => {
            toast.error('Error al actualizar el gasto')
        }
    })
}

const canEdit = (gastoFecha: string) => {
    if (role.value === 'master_admin' || role.value === 'casino_admin') return true
    if (!props.ultimaFechaConfirmada) return true
    return gastoFecha > props.ultimaFechaConfirmada
}

// 🎯 Función para manejar Enter como Tab en el formulario
const handleEnterKey = (event: KeyboardEvent) => {
    if (event.key !== 'Enter') return;
    const target = event.target as HTMLElement;
    
    if (target.tagName === 'BUTTON' && target.getAttribute('type') === 'submit') {
        return;
    }

    event.preventDefault();
    const form = target.closest('form');
    if (!form) return;

    const focusableElements = Array.from(
        form.querySelectorAll(
            'input:not([disabled]):not([readonly]), select:not([disabled]), button:not([disabled]), textarea:not([disabled])'
        )
    ) as HTMLElement[];

    const currentIndex = focusableElements.indexOf(target);
    if (currentIndex > -1 && currentIndex < focusableElements.length - 1) {
        focusableElements[currentIndex + 1].focus();
    }
};

</script>

<template>
    <Head title="Gastos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-xl font-bold">Gasto</h1>

            <form @submit.prevent="                
                form.post('/gastos', {
                    onSuccess: () => {
                        form.tipo_gasto_id = null
                        form.proveedor_id = null
                        form.valor = ''
                        form.descripcion = ''
                    },
                })
                " @keydown="handleEnterKey" class="space-y-4 bg-card p-4 rounded">

                <div class="grid grid-cols-2 gap-4">
                    <div v-if="role === 'master_admin'">
                        <label class="block text-sm">Casino</label>
                        <Select v-model="form.casino_id" class=" w-full">
                            <SelectTrigger class="border w-full">
                                <SelectValue placeholder="Seleccione..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Casinos</SelectLabel>
                                    <SelectItem v-for="c in props.casinos" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    
                    <div v-if="role === 'master_admin' || role === 'casino_admin'">
                        <label class="block text-sm">Sucursal</label>
                        <Select v-model="form.sucursal_id" class=" w-full">
                            <SelectTrigger class="border w-full">
                                <SelectValue placeholder="Seleccione..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Sucursales</SelectLabel>
                                    <SelectItem v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">{{ s.nombre }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm">Fecha</label>
                        <input v-model="form.fecha" required type="date" class="w-full border rounded px-2 py-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm">Tipos gasto</label>
                        <Select v-model="form.tipo_gasto_id" class=" w-full">
                            <SelectTrigger class="border w-full">
                                <SelectValue placeholder="Seleccione..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Tipos gastos</SelectLabel>
                                    <SelectItem v-for="c in props.tipos_gasto" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm">Proveedor</label>
                        <Combobox v-model="form.proveedor_id" by="id" class="w-full">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child class="border w-full">
                                    <Button variant="outline" class="justify-between w-full">
                                        <template v-if="proveedorSeleccionado">
                                            {{ proveedorSeleccionado.identificacion }} - {{ proveedorSeleccionado.nombre }}
                                        </template>
                                        <template v-else>
                                            Seleccione un proveedor
                                        </template>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>

                            <ComboboxList class="w-[var(--radix-popper-anchor-width)]">
                                <div class="relative w-full items-center">
                                    <ComboboxInput
                                        class="pl-9 focus-visible:ring-0 border-0 border-b rounded-none h-10 w-full"
                                        placeholder="Buscar proveedor..." />
                                    <span class="absolute start-0 inset-y-0 flex items-center justify-center px-3">
                                        <Search class="size-4 text-muted-foreground" />
                                    </span>
                                </div>

                                <ComboboxEmpty>No se encontraron proveedores.</ComboboxEmpty>

                                <ComboboxGroup>
                                    <ComboboxItem v-for="m in ProveedoresFiltrados" :key="m.id" :value="m.id">
                                        {{ m.identificacion }} - {{ m.nombre }}
                                        <ComboboxItemIndicator>
                                            <Check class="ml-auto h-4 w-4" />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm">Valor</label>
                        <input v-model.number="form.valor" type="number" class="w-full border rounded px-2 py-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm">Descripción</label>
                        <input v-model="form.descripcion" type="text" class="w-full border rounded px-2 py-1" />
                    </div>
                </div>

                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Guardar</button>
            </form>

            <div class="grid grid-cols-2 gap-4 my-4">
                <div class="p-4 bg-card rounded-lg shadow border text-center">
                    <p class="text-sm text-muted-foreground">Numero de Gastos</p>
                    <p class="text-2xl font-bold">{{ props.total_registros }}</p>
                </div>
                <div class="p-4 bg-card rounded-lg shadow border text-center">
                    <p class="text-sm text-muted-foreground">Total Gastos</p>
                    <p class="text-2xl font-bold text-green-600">{{ formatCurrency(props.total_gastos) }}</p>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <h1 class="text-2xl font-bold">📊 Gastos Registrados</h1>

                <div v-if="(role !== 'master_admin' && role !== 'casino_admin') || form.sucursal_id">
                    <div class="bg-card rounded-lg shadow border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Proveedor</TableHead>
                                    <TableHead>Descripción</TableHead>
                                    <TableHead>Valor</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Sucursal</TableHead>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Acciones</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <TableRow v-for="g in gastos.data" :key="g.id">
                                    <TableCell>
                                        <Badge variant="outline">{{ g.tipo?.nombre ?? 'N/A' }}</Badge>
                                    </TableCell>
                                    <TableCell>{{ g.proveedor?.nombre }}</TableCell>
                                    <TableCell>{{ g.descripcion }}</TableCell>
                                    <TableCell>{{ formatCurrency(g.valor) }}</TableCell>
                                    <TableCell>{{ g.fecha }}</TableCell>
                                    <TableCell>{{ g.sucursal?.nombre ?? '-' }}</TableCell>
                                    <TableCell>{{ g.usuario?.name ?? '-' }}</TableCell>
                                    <TableCell>
                                        <div class="flex gap-2">
                                            <Button 
                                                v-if="canEdit(g.fecha)"
                                                variant="outline" 
                                                size="sm"
                                                @click="openEditModal(g)"
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
                                                    <Button variant="destructive" size="sm" :disabled="!canEdit(g.fecha)" :class="{'opacity-50 cursor-not-allowed': !canEdit(g.fecha)}">
                                                        <Trash2 />
                                                    </Button>
                                                </AlertDialogTrigger>

                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>¿Eliminar Gasto?</AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            Esta acción eliminará el gasto permanentemente.
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                        <AlertDialogAction @click="deleteGasto(g.id)">
                                                            <Trash2 /> Eliminar
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
                </div>
                
                <div class="flex gap-2 mt-4">
                    <a v-for="link in gastos.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'"
                        :class="[
                            'px-3 py-1 rounded text-sm',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                        ]" />
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- MODAL DE EDICIÓN -->
    <Dialog :open="!!editingGasto" @update:open="(val) => !val && (editingGasto = null)">
        <DialogContent class="sm:max-w-[500px] bg-background text-foreground">
            <DialogHeader>
                <DialogTitle>Editar Gasto</DialogTitle>
                <DialogDescription>
                    Modifica los detalles del gasto.
                </DialogDescription>
            </DialogHeader>
            
            <div class="grid gap-4 py-4" v-if="editingGasto">
                <div class="grid gap-2">
                    <label class="text-sm font-medium">Fecha</label>
                    <input v-model="editForm.fecha" type="date" class="w-full border rounded px-2 py-1" />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium">Tipo gasto</label>
                    <Select v-model="editForm.tipo_gasto_id" class="w-full">
                        <SelectTrigger class="border w-full">
                            <SelectValue placeholder="Seleccione..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Tipos gastos</SelectLabel>
                                <SelectItem v-for="c in props.tipos_gasto" :key="c.id" :value="c.id">{{ c.nombre }}</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium">Proveedor</label>
                    <div class="relative mb-2">
                        <input 
                            v-model="proveedorSearch" 
                            type="text" 
                            placeholder="Buscar proveedor..." 
                            class="w-full border rounded px-9 py-2 text-sm"
                        />
                        <span class="absolute left-0 inset-y-0 flex items-center justify-center px-3">
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                    </div>
                    <Select v-model="editForm.proveedor_id" class="w-full">
                        <SelectTrigger class="border w-full">
                            <SelectValue placeholder="Seleccione..." />
                        </SelectTrigger>
                        <SelectContent class="max-h-[300px]">
                            <SelectGroup>
                                <SelectLabel>Proveedores ({{ ProveedoresFiltradosModal.length }})</SelectLabel>
                                <SelectItem v-for="p in ProveedoresFiltradosModal" :key="p.id" :value="p.id">
                                    {{ p.identificacion }} - {{ p.nombre }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium">Valor</label>
                    <input v-model.number="editForm.valor" type="number" class="w-full border rounded px-2 py-1" />
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium">Descripción</label>
                    <input v-model="editForm.descripcion" type="text" class="w-full border rounded px-2 py-1" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="editingGasto = null">Cancelar</Button>
                <Button @click="updateGasto" :disabled="editForm.processing">
                    Guardar Cambios
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>