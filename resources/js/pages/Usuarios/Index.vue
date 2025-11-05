<script setup lang="ts">

import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableCaption } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

import { useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'

import { Check, ChevronsUpDown, Search, Trash2, Pencil, Plus } from "lucide-vue-next"
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger } from "@/components/ui/combobox"

import { Checkbox } from '@/components/ui/checkbox'

import { Switch } from '@/components/ui/switch'

import { Badge } from '@/components/ui/badge'

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'

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
        title: 'Usuarios',
        href: '/usuarios',
    },
];

const props = defineProps<{
    users: any,
    roles: Array<{ id: number, nombre: string }>,
    casinos: Array<{ id: number, nombre: string }>,
    sucursales: Array<{ id: number, nombre: string, casino_id: number }>,
    user: { id: number, name: string, roles: string[], sucursal_id?: number, casino_id?: number }
}>()


const form = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    role: '',
    casino_id: null,
    sucursal_id: null,
})

// Rol activo
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

const toggleEstado = (usuario, nuevoEstado) => {
    const estadoAnterior = usuario.activo
    usuario.activo = nuevoEstado ? 1 : 0

    router.patch(`/usuarios/${usuario.id}/toggle`, { activo: usuario.activo }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Estado actualizado', {
                description: `El usuario ${usuario.name} ahora está ${nuevoEstado ? 'activo' : 'inactivo'}.`,
            })
        },
        onError: () => {
            usuario.activo = estadoAnterior
            toast.error('Error al actualizar el estado', {
                description: 'No se pudo cambiar el estado del usuario.',
            })
        },
    })
}

// const deleteUser = (id: number) => {
//     router.delete(`/usuarios/${id}`, {
//         preserveScroll: true,
//         onSuccess: () => {
//             toast.success('Usuario eliminado', {
//                 description: 'El usuario fue eliminado correctamente.',
//             })
//         },
//         onError: (errors) => {
//             toast.error('Error al eliminar', {
//                 description: 'No se pudo eliminar el usuario.',
//             })
//         },
//     })
// }

const page = usePage()

watchEffect(() => {
    const errors = page.props.errors || {}
    Object.entries(errors).forEach(([field, message]) => {
        if (message) {
            toast.error('Error al guardar usuario', {
                description: String(message),
            })
        }
    })
})

// Estado del modal
const isModalOpen = ref(false)
const isEditing = ref(false)
const editingUserId = ref<number | null>(null)

// Abrir modal para crear
const openCreateModal = () => {
    isEditing.value = false
    editingUserId.value = null
    form.reset()
    isModalOpen.value = true
}

// Abrir modal para editar
const openEditModal = (usuario: any) => {
    isEditing.value = true
    editingUserId.value = usuario.id

    form.id = usuario.id
    form.name = usuario.name
    form.email = usuario.email
    form.password = ''
    form.role = usuario.roles[0]?.name ?? ''
    form.casino_id = usuario.casino_id
    form.sucursal_id = usuario.sucursal_id

    isModalOpen.value = true
}

// Cerrar modal
const closeModal = () => {
    isModalOpen.value = false
    form.reset()
    form.clearErrors()
}

// Guardar (crear o editar)
const saveUser = () => {
    if (isEditing.value && editingUserId.value) {
        // Editar usuario
        form.put(`/usuarios/${editingUserId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal()
                toast.success('Usuario actualizado', {
                    description: 'El usuario fue actualizado correctamente.',
                })
            },
            onError: (errors) => {
                console.error('Errores de validación:', errors)
            }
        })
    } else {
        // Crear usuario
        form.post('/usuarios', {
            preserveScroll: true,
            onSuccess: () => {
                closeModal()
                toast.success('Usuario creado', {
                    description: 'El usuario fue creado correctamente.',
                })
            },
            onError: (errors) => {
                console.error('Errores de validación:', errors)
            }
        })
    }
}

</script>

<template>

    <Head title="Usuarios" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">

            <h1 class="text-xl font-bold">Usuarios</h1>

            Formulario de creación de usuario
            <form @submit.prevent="form.post('/usuarios')" class="space-y-4 bg-card p-4 rounded">

                Select casino (solo para master_admin)
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
                    Select sucursal (para master_admin y casino_admin)
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

                <div>
                    <label class="block text-sm">Nombre</label>
                    <input v-model="form.name" class="border rounded w-full mb-2" />
                </div>

                <div>
                    <label class="block text-sm">Correo</label>
                    <input v-model="form.email" class="border rounded w-full mb-2" />
                </div>

                <div>
                    <label class="block text-sm">Password</label>
                    <input v-model="form.password" type="password" class="border rounded w-full mb-2" />
                </div>

                <div>
                    <label class="block text-sm">Roles</label>
                    <Select v-model="form.role" class=" w-full">
                        <SelectTrigger class="border w-full">
                            <SelectValue placeholder="Seleccione..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Selecciona un rol</SelectLabel>
                                <SelectItem v-for="r in props.roles" :key="r.id" :value="r.id">{{ r.name
                                    }}</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                </div>


                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">
                   
                </button>
            </form>

        </div> -->


        <div class="p-6 space-y-6">
             <!-- Header con botón crear -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">👥 Usuarios</h1>                
            </div>

                <Button @click="openCreateModal">
                    <Plus class="h-4 w-4 mr-2" />
                    Nuevo Usuario
                </Button>

            <!-- Tabla -->
            <div class="bg-card rounded-lg shadow border border-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <!-- <TableHead>ID</TableHead> -->
                            <TableHead>Nombre</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Rol</TableHead>
                            <TableHead>Casino</TableHead>
                            <TableHead>Sucursal</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Acciones</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-for="u in props.users.data" :key="u.id">
                            <!-- <TableCell>{{ l.id }}</TableCell> -->
                            <TableCell>{{ u.name }}</TableCell>
                            <TableCell>{{ u.email }}</TableCell>
                            <TableCell>{{ u.roles[0]?.name ?? 'Sin rol' }}</TableCell>
                            <TableCell>{{ u.casino?.nombre }}</TableCell>
                            <TableCell>{{ u.sucursal?.nombre }}</TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Switch :model-value="Boolean(u.activo)"
                                        @update:model-value="(val: boolean) => toggleEstado(u, val)" />
                                    <Badge :variant="u.activo ? 'default' : 'secondary'" class="capitalize">
                                        {{ u.activo ? 'Activo' : 'Inactivo' }}
                                    </Badge>

                                </div>
                            </TableCell>

                            <TableCell>
                                <Button variant="outline" size="sm" @click="openEditModal(u)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>


            <div class="flex gap-2 mt-4">
                <a v-for="link in users.links" :key="link.label" v-html="link.label" :href="link.url ?? '#'" :class="[
                    'px-3 py-1 rounded text-sm',
                    link.active
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                ]" />
            </div>
        </div>



        <!-- Modal de Crear/Editar -->
        <Dialog v-model:open="isModalOpen" class="space-y-4">
            <DialogContent class="sm:max-w-[800px] space-y-4 bg-card p-4 rounded">
                <DialogHeader>
                    <DialogTitle>
                        {{ isEditing ? 'Editar Usuario' : 'Crear Nuevo Usuario' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ isEditing ? 'Modifica los datos del usuario.' : 'Completa los datos para crear un nuevo usuario.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="saveUser" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="name">Nombre</Label>
                            <Input id="name" v-model="form.name" placeholder="Nombre completo" required />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Correo</Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="correo@ejemplo.com"
                                required />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="password">
                                Contraseña
                                <span v-if="isEditing" class="text-xs text-muted-foreground">(dejar vacío para no
                                    cambiar)</span>
                            </Label>
                            <Input id="password" v-model="form.password" type="password" placeholder="••••••••"
                                :required="!isEditing" />
                        </div>

                        <div class="space-y-2">
                            <Label for="role">Rol</Label>
                            <Select v-model="form.role">
                                <SelectTrigger id="role">
                                    <SelectValue placeholder="Seleccione un rol..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Roles disponibles</SelectLabel>
                                        <SelectItem v-for="r in props.roles" :key="r.id" :value="r.name">
                                            {{ r.name }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Casino y Sucursal (condicional) -->
                    <div class="grid grid-cols-2 gap-4">
                        <div v-if="role === 'master_admin'" class="space-y-2">
                            <Label for="casino">Casino</Label>
                            <Select v-model="form.casino_id">
                                <SelectTrigger id="casino">
                                    <SelectValue placeholder="Seleccione casino..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Casinos</SelectLabel>
                                        <SelectItem v-for="c in props.casinos" :key="c.id" :value="c.id">
                                            {{ c.nombre }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="role === 'master_admin' || role === 'casino_admin'" class="space-y-2">
                            <Label for="sucursal">Sucursal</Label>
                            <Select v-model="form.sucursal_id">
                                <SelectTrigger id="sucursal">
                                    <SelectValue placeholder="Seleccione sucursal..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Sucursales</SelectLabel>
                                        <SelectItem v-for="s in sucursalesFiltradas" :key="s.id" :value="s.id">
                                            {{ s.nombre }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeModal">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>


    </AppLayout>








</template>
