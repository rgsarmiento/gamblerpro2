<script setup lang="ts">

import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableCaption } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import { useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

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

import { toast } from 'vue-sonner' // 👈 nuevo sistema

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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Gastos',
        href: '/gastos',
    },
];

// Formulario de creación de lecturas
const form = useForm({
    casino_id: null as number | null,
    sucursal_id: null as number | null,
    tipo_gasto_id: null as number | null,
    proveedor_id: null as number | null,
    valor: '',
    descripcion: '',
    fecha: new Date().toISOString().split('T')[0], // fecha actual por defecto
})

// Props desde Laravel
const props = defineProps<{
    gastos: any
    total_registros: number
    total_gastos: number
    casinos: Array<{ id: number, nombre: string }>
    sucursales: Array<{ id: number, nombre: string, casino_id: number }>
    tipos_gasto: Array<{ id: number; nombre: string }>
    proveedores: Array<{ id: number; identificacion: string; nombre: string; sucursal_id: number }>
    user: { id: number, name: string, roles: string[], sucursal_id?: number, casino_id?: number }
}>()

// Inicializar casino y sucursal del usuario logueado
if (props.user.roles.includes('cajero') || props.user.roles.includes('sucursal_admin')) {
    form.casino_id = props.user.casino_id ?? null
    form.sucursal_id = props.user.sucursal_id ?? null
}

// Rol activo
const role = computed(() => props.user.roles[0] ?? '')

// Filtrar sucursales según casino seleccionado
const sucursalesFiltradas = computed(() => {
    if (role.value === 'master_admin') {
        return props.sucursales.filter(s => s.casino_id == form.casino_id)
    }
    if (role.value === 'casino_admin') {
        return props.sucursales
    }
    return []
})

// Filtrar máquinas según sucursal seleccionada
const ProveedoresFiltrados = computed(() => {
    if (role.value === 'master_admin' || role.value === 'casino_admin') {
        return props.proveedores.filter(m => m.sucursal_id == form.sucursal_id)
    }
    if (role.value === 'sucursal_admin' || role.value === 'cajero') {
        return props.proveedores.filter(m => m.sucursal_id == props.user.sucursal_id)
    }
    return []
})

// Buscar proveedor seleccionad0
const proveedorSeleccionado = computed(() =>
    props.proveedores.find(m => m.id == form.proveedor_id)
)


const deleteGasto = async (id: number) => {
    router.delete(`/gastos/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            // ✅ Redirige o refresca la lista
            router.visit('/gastos', { replace: true })
            // ✅ Puedes mostrar un toast aquí si usas shadcn o similar
            toast.success('Eliminada correctamente', {
                description: 'El gasto fue eliminado y el total recalculado.',
            })
        },
        onError: (errors) => {
            console.error('Error al eliminar el gasto:', errors)
        },
    })
}


const formatCurrency = (value) => {
    // if (typeof value !== 'number') return '';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(value);
};


const page = usePage()

watchEffect(() => {
    // Asegúrate de que existan errores
    const errors = page.props.errors || {}

    // Si hay errores, recórrelos y muestra cada uno
    Object.entries(errors).forEach(([field, message]) => {
        if (message) {
            console.log(message);
            toast.error('Error al guardar lectura', {
                description: String(message),
            })
        }
    })
})


// === ✅ Recarga dinámica de tabla según filtros ===
watch(
    [() => form.fecha, () => form.casino_id, () => form.sucursal_id],
    ([newFecha, newCasino, newSucursal]) => {
        // Evitar consultas vacías si se requiere selección
        if (
            (role.value === 'master_admin' && !newSucursal) ||
            (role.value === 'casino_admin' && !newSucursal)
        ) {
            return
        }

        // 🔄 Recargar datos con los nuevos filtros
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


</script>


<template>

    <Head title="Gastos" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <h1 class="text-xl font-bold">Gasto</h1>


            <!-- Formulario de nueva lectura -->
            <form @submit.prevent="                
                form.post('/gastos', {
                    onSuccess: () => {
                        // Limpia solo los campos de la lectura
                        form.tipo_gasto_id = null
                        form.proveedor_id = null
                        form.valor = ''
                        form.descripcion = ''
                    },
                })
                " class="space-y-4 bg-card p-4 rounded">

                <!-- Select casino (solo para master_admin) -->
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
                                    <SelectItem v-for="c in props.casinos" :key="c.id" :value="c.id">{{ c.nombre }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>


                    </div>
                    <!-- Select sucursal (para master_admin y casino_admin) -->
                    <div v-if="role === 'master_admin' || role === 'casino_admin'">
                        <label class="block text-sm">Sucursal</label>
                        <Select v-model="form.sucursal_id" class=" w-full">
                            <SelectTrigger class="border w-full">
                                <SelectValue placeholder="Seleccione..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Sucursales</SelectLabel>
                                    <SelectItem v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">{{ s.nombre
                                        }}</SelectItem>
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
                                    <SelectItem v-for="c in props.tipos_gasto" :key="c.id" :value="c.id">{{ c.nombre }}
                                    </SelectItem>
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
                                            {{ proveedorSeleccionado.identificacion }} - {{ proveedorSeleccionado.nombre
                                            }}
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
                                        placeholder="Buscar máquina..." />
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

                <!-- Tabla -->
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

                                    <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="destructive" size="sm">
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


                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
                <!-- Paginación -->
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


</template>