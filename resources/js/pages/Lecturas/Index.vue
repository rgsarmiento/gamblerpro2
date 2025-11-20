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

import { Check, ChevronsUpDown, Search, Trash2, Edit2 } from "lucide-vue-next"
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
        title: 'Lecturas',
        href: '/lecturas',
    },
];

// Formulario de creación de lecturas
const form = useForm({
    casino_id: null as number | null,
    sucursal_id: null as number | null,
    maquina_id: null as number | null,
    entrada: '',
    salida: '',
    jackpots: '',
    fecha: new Date().toISOString().split("T")[0], // ⬅️ FECHA INICIAL
    neto_inicial: 0,
    neto_final: 0,
    total_creditos: 0,
    total_recaudo: 0,
})

// Props desde Laravel
const props = defineProps<{
    lecturas: any
    ultimaFechaConfirmada: string | null
    lecturas_confirmadas: boolean
    pendientes: boolean
    total_registros: number
    total_recaudado: number
    casinos: Array<{ id: number, nombre: string }>
    sucursales: Array<{ id: number, nombre: string, casino_id: number }>
    maquinas: Array<{ id: number; ndi: string; nombre: string; denominacion: number; sucursal_id: number; ultimo_neto_final: number }>
    user: { id: number, name: string, roles: string[], sucursal_id?: number, casino_id?: number }
}>()

// Si no existe una lectura previa → permitir hoy como mínimo
const minFecha = computed(() => {
    if (!props.ultimaFechaConfirmada) {
        return new Date().toISOString().split("T")[0]
    }

    // convertir a YYYY-MM-DD
    return props.ultimaFechaConfirmada.substring(0, 10)
})

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
const maquinasFiltradas = computed(() => {
    if (role.value === 'master_admin' || role.value === 'casino_admin') {
        return props.maquinas.filter(m => m.sucursal_id == form.sucursal_id)
    }
    if (role.value === 'sucursal_admin' || role.value === 'cajero') {
        return props.maquinas.filter(m => m.sucursal_id == props.user.sucursal_id)
    }
    return []
})

// Buscar máquina seleccionada
const maquinaSeleccionada = computed(() =>
    props.maquinas.find(m => m.id == form.maquina_id)
)

// Neto inicial viene del último cierre de esa máquina
//const netoInicial = computed(() => maquinaSeleccionada.value?.ultimo_neto_final ?? 0)

// Cuando cambia la máquina, actualiza el neto inicial en el formulario
// watchEffect(() => {
//     if (maquinaSeleccionada.value) {
//         form.neto_inicial = formatNumber(maquinaSeleccionada.value.ultimo_neto_final) ?? 0
//     }
// })
watch(
    () => form.maquina_id,
    (newId) => {
        const m = props.maquinas.find(maquina => maquina.id === newId)
        form.neto_inicial = m ? formatNumber(m.ultimo_neto_final) : 0
    }
)


// 🧭 watcher para recargar lecturas según casino / sucursal
watch(
    () => [form.casino_id, form.sucursal_id],
    ([casino, sucursal]) => {
        // Si el usuario es master o casino admin, asegurarse de que haya selección antes de cargar
        if ((role.value === 'master_admin' || role.value === 'casino_admin') && !sucursal) return

        router.visit('/lecturas', {
            method: 'get',
            preserveScroll: true,
            preserveState: true,
            data: {
                casino_id: casino,
                sucursal_id: sucursal,
                fecha: form.fecha || null,
            },
        })
    }
)


// Cálculos automáticos
const netoFinal = computed(() =>
    Number(form.entrada) - Number(form.salida) - Number(form.jackpots)
)

const totalCreditos = computed(() => netoFinal.value - Number(form.neto_inicial))

const totalRecaudo = computed(() =>
    totalCreditos.value * (maquinaSeleccionada.value?.denominacion ?? 0)
)


const deleteLectura = async (id: number) => {
    router.delete(`/lecturas/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            // ✅ Redirige o refresca la lista
            router.visit('/lecturas', { replace: true })
            // ✅ Puedes mostrar un toast aquí si usas shadcn o similar
            toast.success('Eliminada correctamente', {
                description: 'La lectura fue eliminada y los totales recalculados.',
            })
        },
        onError: (errors) => {
            console.error('Error al eliminar la lectura:', errors)
        },
    })
}

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

const confirmarLecturas = () => {
    if (!confirm('¿Deseas confirmar todas las lecturas pendientes?')) return

    router.post('/lecturas/confirmar', {
        sucursal_id: form.sucursal_id // <-- se enviará solo si es master o casino_admin
    }, {
        onSuccess: () => {
            toast.success('Lecturas confirmadas correctamente.')
        },
        onError: (errors) => {
            toast.error(errors.lecturas ?? 'Error al confirmar lecturas')
        }
    })
}

watch(() => form.fecha, (value) => {
    if (!value) return

    router.visit('/lecturas', {
        method: 'get',
        preserveScroll: true,
        preserveState: true,
        data: {
            casino_id: form.casino_id,
            sucursal_id: form.sucursal_id,
            fecha: form.fecha,
        },
    })
})



// --- NUEVO: estado y formulario para editar ---
const editModalOpen = ref(false)
const editForm = useForm({
    id: null as number | null,
    neto_inicial: 0,
    entrada: 0,
    salida: 0,
    jackpots: 0,
})

// NUEVO: denominación de la máquina editada y cálculos reactivos
const editDenominacion = ref(0)
const editMaquinaLabel = ref('')

const editNetoFinal = computed(() =>
    Number(editForm.entrada) - Number(editForm.salida) - Number(editForm.jackpots)
)

const editTotalCreditos = computed(() => editNetoFinal.value - Number(editForm.neto_inicial))

const editTotalRecaudo = computed(() =>
    editTotalCreditos.value * (editDenominacion.value ?? 0)
)

const canEdit = (l: any) => {
    return l.confirmado == 0 || role.value === 'master_admin'
}

const openEdit = (l: any) => {
    if (!canEdit(l)) {
        toast.error('No tienes permiso para editar esta lectura')
        return
    }
    editForm.reset()
    editForm.id = l.id
    editForm.neto_inicial = Number(l.neto_inicial) || 0
    editForm.entrada = Number(l.entrada) || 0
    editForm.salida = Number(l.salida) || 0
    editForm.jackpots = Number(l.jackpots) || 0

    // NUEVO: setear denominación de la máquina para el cálculo del recaudo
    editDenominacion.value = l.maquina?.denominacion ?? 0
    editMaquinaLabel.value = `${l.maquina?.ndi} - ${l.maquina?.nombre} • Den: ${formatNumber(editDenominacion.value)}`

    editModalOpen.value = true
}

const submitEdit = () => {
    if (!editForm.id) return
    editForm.put(`/lecturas/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Lectura actualizada')
            editModalOpen.value = false
            // refrescar lista para ver recalculos
            router.visit('/lecturas', {
                method: 'get',
                preserveScroll: true,
                preserveState: true,
                data: {
                    casino_id: form.casino_id,
                    sucursal_id: form.sucursal_id,
                    fecha: form.fecha || null,
                },
            })
        },
        onError: (errors) => {
            toast.error('Error al actualizar lectura')
            console.error(errors)
        },
    })
}



</script>


<template>

    <Head title="Lecturas" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <h1 class="text-xl font-bold">Lecturas</h1>


            <!-- Formulario de nueva lectura -->
            <form @submit.prevent="
                form.neto_inicial = form.neto_inicial;
            form.neto_final = netoFinal;
            form.total_creditos = totalCreditos;
            form.total_recaudo = totalRecaudo

            form.post('/lecturas', {
                onSuccess: () => {
                    // Limpia solo los campos de la lectura
                    form.maquina_id = null
                    form.entrada = ''
                    form.salida = ''
                    form.jackpots = ''
                    form.neto_inicial = 0
                    form.neto_final = 0
                    form.total_creditos = 0
                    form.total_recaudo = 0
                },
            })
                " class="space-y-4 bg-card p-4 rounded">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha de Lectura</label>
                        <!-- <input type="date" v-model="form.fecha" :min="minFecha" -->
                        <input type="date" v-model="form.fecha" class="border rounded px-2 py-1 w-full" />
                    </div>
                </div>


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


                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm">Maquinas</label>
                        <Combobox v-model="form.maquina_id" by="id" class="w-full">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child class="border w-full">
                                    <Button variant="outline" class="justify-between w-full">
                                        <template v-if="maquinaSeleccionada">
                                            {{ maquinaSeleccionada.ndi }} - {{ maquinaSeleccionada.nombre }} • Den: {{
                                                formatNumber(maquinaSeleccionada.denominacion) }}
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

                                        <div>
                                            <p class="font-semibold">
                                                {{ m.ndi }} — {{ m.nombre }} • Den: {{ formatNumber(m.denominacion) }}
                                            </p>
                                        </div>

                                        <ComboboxItemIndicator>
                                            <Check class="ml-auto h-4 w-4" />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                    </div>

                </div>
                <!-- Entradas -->
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Neto Inicial</label>
                        <div class="flex gap-2">
                            <input v-model.number="form.neto_inicial" type="number" step="0.01"
                                :readonly="role === 'cajero'" :class="[
                                    'w-full border rounded px-2 py-1',
                                    role === 'cajero' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''
                                ]" placeholder="Automático" />
                            <Button
                                v-if="role !== 'cajero' && maquinaSeleccionada && form.neto_inicial !== maquinaSeleccionada.ultimo_neto_final"
                                type="button" variant="outline" size="icon"
                                @click="form.neto_inicial = formatNumber(maquinaSeleccionada.ultimo_neto_final)"
                                title="Restaurar valor inicial">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                                    <path d="M21 3v5h-5" />
                                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                                    <path d="M8 16H3v5" />
                                </svg>
                            </Button>
                        </div>
                        <p v-if="role !== 'cajero' && maquinaSeleccionada && formatNumber(form.neto_inicial) !== formatNumber(maquinaSeleccionada.ultimo_neto_final)"
                            class="text-xs text-amber-600 mt-1">
                            ⚠️ Valor modificado (Original: {{ formatNumber(maquinaSeleccionada.ultimo_neto_final) }})
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm">Entrada</label>
                        <input v-model.number="form.entrada" type="number" class="w-full border rounded px-2 py-1" />
                    </div>
                    <div>
                        <label class="block text-sm">Salida</label>
                        <input v-model.number="form.salida" type="number" class="w-full border rounded px-2 py-1" />
                    </div>
                    <div>
                        <label class="block text-sm">Jackpots</label>
                        <input v-model.number="form.jackpots" type="number" class="w-full border rounded px-2 py-1" />
                    </div>
                </div>

                <!-- Preview cálculos -->
                <div class="p-4 bg-muted rounded">
                    <p>Neto inicial: <strong>{{ formatNumber(form.neto_inicial) }}</strong></p>
                    <p>Neto final: <strong>{{ formatNumber(netoFinal) }}</strong></p>
                    <p>Total créditos: <strong>{{ formatNumber(totalCreditos) }}</strong></p>
                    <p>Total recaudado: <strong>{{ formatCurrency(totalRecaudo) }}</strong></p>
                </div>

                <button type="submit" v-if="!(role === 'cajero' && props.lecturas_confirmadas)"
                    class="bg-indigo-600 text-white px-4 py-2 rounded">
                    Guardar
                </button>

                <div v-if="role === 'cajero' && props.lecturas_confirmadas"
                    class="bg-red-500/20 text-red-500 border border-red-500 px-4 py-2 rounded mb-4">
                    ⚠️ Las lecturas de esta fecha ya están confirmadas.
                    No se pueden ingresar nuevas lecturas.
                </div>
            </form>

            <div class="grid grid-cols-2 gap-4 my-4">
                <div class="p-4 bg-card rounded-lg shadow border text-center">
                    <p class="text-sm text-muted-foreground">Total Lecturas</p>
                    <p class="text-2xl font-bold">{{ props.total_registros }}</p>
                </div>
                <div class="p-4 bg-card rounded-lg shadow border text-center">
                    <p class="text-sm text-muted-foreground">Total recaudado</p>
                    <p class="text-2xl font-bold text-green-600">{{ formatCurrency(props.total_recaudado) }}</p>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <h1 class="text-2xl font-bold">📊 Lecturas de Máquinas</h1>
                

                <div v-if="props.pendientes && !props.lecturas_confirmadas" class="flex gap-4 mb-4 justify-end">
                    <Button class="bg-green-600 text-white hover:bg-green-700" @click="confirmarLecturas"
                        :disabled="(role === 'master_admin' || role === 'casino_admin') && !form.sucursal_id">
                        Confirmar lecturas
                    </Button>
                </div>


                <!-- Si no hay pendientes -->
                <p v-else class="text-sm text-muted-foreground text-center my-4">
                    ✅ Todas las lecturas ya fueron confirmadas.
                </p>

                <div v-if="props.lecturas_confirmadas"
                    class="bg-red-500/20 text-red-500 border border-red-500 px-4 py-2 rounded mb-4">
                    Estas lecturas ya están confirmadas. No podrán editarse ni eliminarse,
                    excepto por un usuario <strong>master_admin</strong>.
                </div>


                <!-- Tabla -->
                <div class="bg-card rounded-lg shadow border border-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <!-- <TableHead>ID</TableHead> -->
                                <TableHead>Ndi</TableHead>
                                <TableHead>Máquina</TableHead>
                                <TableHead>Entrada</TableHead>
                                <TableHead>Salida</TableHead>
                                <TableHead>Jackpots</TableHead>
                                <TableHead>Neto Final</TableHead>
                                <TableHead>Neto Inicial</TableHead>
                                <TableHead>Créditos</TableHead>
                                <TableHead>Recaudo</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Acciones</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow v-for="l in lecturas.data" :key="l.id">
                                <!-- <TableCell>{{ l.id }}</TableCell> -->
                                <TableCell>{{ l.maquina?.ndi }}</TableCell>
                                <TableCell>{{ l.maquina?.nombre }}</TableCell>
                                <TableCell>{{ formatNumber(l.entrada) }}</TableCell>
                                <!-- <TableCell>{{ l.sucursal?.nombre }}</TableCell> -->
                                <TableCell>{{ formatNumber(l.salida) }}</TableCell>
                                <TableCell>{{ formatNumber(l.jackpots) }}</TableCell>
                                <TableCell>{{ formatNumber(l.neto_final) }}</TableCell>
                                <TableCell>{{ formatNumber(l.neto_inicial) }}</TableCell>
                                <TableCell>{{ formatNumber(l.total_creditos) }}</TableCell>
                                <TableCell>{{ formatCurrency(l.total_recaudo) }}</TableCell>
                                <TableCell>{{ l.fecha }}</TableCell>
                                <TableCell>

                                    <!-- <AlertDialog>
                                        <AlertDialogTrigger as-child>
                                            <Button variant="destructive" size="sm">
                                                <Trash2 />
                                            </Button>
                                        </AlertDialogTrigger>

                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>¿Eliminar lectura?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Esta acción eliminará la lectura permanentemente.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                <AlertDialogAction @click="deleteLectura(l.id)">
                                                    <Trash2 /> Eliminar
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog> -->

                                    <!-- Botón editar -->
                                    <Button variant="ghost" size="sm" @click="openEdit(l)"
                                        :disabled="!(l.confirmado == 0 || role === 'master_admin')" class="mr-2">
                                        <Edit2 />
                                    </Button>

                                    <!-- 🔥 Si lectura NO confirmada → cualquiera puede eliminar -->
                                    <AlertDialog v-if="l.confirmado == 0">
                                        <AlertDialogTrigger as-child>
                                            <Button variant="destructive" size="sm">
                                                <Trash2 />
                                            </Button>
                                        </AlertDialogTrigger>

                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>¿Eliminar lectura?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Esta acción eliminará la lectura permanentemente.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                <AlertDialogAction @click="deleteLectura(l.id)">
                                                    <Trash2 /> Eliminar
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>

                                    <!-- 🔥 SI está confirmada → SOLO master_admin puede eliminar -->
                                    <AlertDialog v-else-if="role === 'master_admin'">
                                        <AlertDialogTrigger as-child>
                                            <Button variant="destructive" size="sm">
                                                <Trash2 />
                                            </Button>
                                        </AlertDialogTrigger>

                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>¿Eliminar lectura confirmada?</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Esta lectura ya fue confirmada. Solo un master_admin puede
                                                    eliminarla.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                                                <AlertDialogAction @click="deleteLectura(l.id)">
                                                    <Trash2 /> Eliminar
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>

                                    <!-- 🔒 Cualquier otro rol NO puede eliminar lecturas confirmadas -->
                                    <Button v-else variant="destructive" size="sm" disabled
                                        class="opacity-50 cursor-not-allowed">
                                        <Trash2 />
                                    </Button>

                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Paginación -->
                <div class="flex gap-2 mt-4">
                    <a v-for="link in lecturas.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'"
                        :class="[
                            'px-3 py-1 rounded text-sm',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                        ]" />
                </div>
            </div>
        </div>


        <!-- NUEVO: Modal simple para editar lectura -->
        <div v-if="editModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-card p-6 rounded w-full max-w-lg">
                <h2 class="text-lg font-bold mb-3">Editar lectura</h2>
                <h3 class="text-lg font-bold mb-3">{{ editMaquinaLabel }}</h3>
                <form @submit.prevent="submitEdit" class="space-y-3">
                    <div class="grid grid-cols-4 gap-3">
                        <div>
                            <label class="block text-sm">Neto Inicial</label>
                            <input v-model.number="editForm.neto_inicial" type="number" step="0.01"
                                :readonly="role === 'cajero'" :class="[
                                    'w-full border rounded px-2 py-1',
                                    role === 'cajero' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''
                                ]" placeholder="Automático" />
                        </div>
                        <div>
                            <label class="block text-sm">Entrada</label>
                            <input v-model.number="editForm.entrada" type="number" step="0.01"
                                class="w-full border rounded px-2 py-1" />
                        </div>
                        <div>
                            <label class="block text-sm">Salida</label>
                            <input v-model.number="editForm.salida" type="number" step="0.01"
                                class="w-full border rounded px-2 py-1" />
                        </div>
                        <div>
                            <label class="block text-sm">Jackpots</label>
                            <input v-model.number="editForm.jackpots" type="number" step="0.01"
                                class="w-full border rounded px-2 py-1" />
                        </div>
                    </div>

                    <!-- NUEVO: Preview de cálculos -->
                    <div class="p-4 bg-muted rounded mt-3">
                        <p>Neto inicial: <strong>{{ formatNumber(editForm.neto_inicial) }}</strong></p>
                        <p>Neto final: <strong>{{ formatNumber(editNetoFinal) }}</strong></p>
                        <p>Total créditos: <strong>{{ formatNumber(editTotalCreditos) }}</strong></p>
                        <p>Total recaudado: <strong>{{ formatCurrency(editTotalRecaudo) }}</strong></p>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <Button type="button" variant="outline" @click="editModalOpen = false">Cancelar</Button>
                        <Button type="submit">Guardar</Button>
                    </div>
                </form>
            </div>
        </div>

    </AppLayout>


</template>