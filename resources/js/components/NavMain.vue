<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import { urlIsActive } from '@/lib/utils'
import type { NavItem } from '@/types'
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ref, onMounted, watch, computed } from 'vue'

// Props
defineProps<{ items: NavItem[] }>()

// Usuario desde Inertia
const page = usePage()
const user = computed(() => page.props.auth?.user)

// Normalizar roles a array de strings
const rolesUsuario = computed<string[]>(() => {
    const roles = (user.value as any)?.roles ?? []
    const roleNames = roles.map((r: any) => r.name)
    console.log('🔐 Roles del usuario:', roleNames)
    return roleNames
})

/** 🔹 Función de permisos por rol */
function tieneAcceso(item: NavItem): boolean {  
    console.log(`🔍 Verificando acceso para "${item.title}":`, {
        rolesRequeridos: item.roles,
        rolesUsuario: rolesUsuario.value,
        tieneRoles: !item.roles || item.roles.length === 0,
    })
    
    if (!item.roles || item.roles.length === 0) {
        console.log(`✅ "${item.title}" - Acceso público`)
        return true
    }
    
    const tienePermiso = item.roles.some(r => rolesUsuario.value.includes(r))
    console.log(`${tienePermiso ? '✅' : '❌'} "${item.title}" - Permiso: ${tienePermiso}`)
    return tienePermiso
}

/** 🔹 Verificar si al menos un subitem es accesible */
function tieneSubitemsAccesibles(item: NavItem): boolean {
    if (!item.items || item.items.length === 0) return false
    return item.items.some(subItem => tieneAcceso(subItem))
}

// Grupos abiertos persistentes
const openGroups = ref<string[]>([])

onMounted(() => {
    const saved = localStorage.getItem('sidebar-open-groups')
    if (saved) {
        try {
            openGroups.value = JSON.parse(saved)
        } catch { 
            openGroups.value = [] 
        }
    }
})

watch(openGroups, (val) => {
    localStorage.setItem('sidebar-open-groups', JSON.stringify(val))
}, { deep: true })

function toggleGroup(title: string, isOpen: boolean) {
    if (isOpen) {
        if (!openGroups.value.includes(title)) openGroups.value.push(title)
    } else {
        openGroups.value = openGroups.value.filter(t => t !== title)
    }
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Plataforma</SidebarGroupLabel>
        <SidebarMenu>

            <template v-for="item in items" :key="String(item.href ?? item.title)">

                <!-- 🔹 GRUPO (colapsable) -->
                <template v-if="item.isGroup && item.items?.length">
                    
                    <!-- Solo mostrar el grupo si el usuario tiene acceso al grupo Y hay subitems accesibles -->
                    <Collapsible
                        v-if="tieneAcceso(item) && tieneSubitemsAccesibles(item)"
                        as-child
                        :open="openGroups.includes(item.title)"
                        @update:open="open => toggleGroup(item.title, open)"
                        class="group/collapsible"
                    >
                        <SidebarMenuItem>

                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="item.title">
                                    <component :is="item.icon" v-if="item.icon" />
                                    <span>{{ item.title }}</span>
                                    <ChevronRight
                                        class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                    />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>

                            <CollapsibleContent class="transition-[height] duration-200 ease-in-out">
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-for="subItem in item.items"
                                        :key="String(subItem.href ?? subItem.title)"
                                    >
                                        <!-- Solo mostrar subitems accesibles -->
                                        <SidebarMenuSubButton
                                            v-if="tieneAcceso(subItem)"
                                            as-child
                                            :is-active="urlIsActive(subItem.href, page.url)"
                                            :class="{
                                                'bg-primary/10 text-primary':
                                                    urlIsActive(subItem.href, page.url),
                                            }"
                                        >
                                            <Link :href="subItem.href">
                                                <component :is="subItem.icon" v-if="subItem.icon" />
                                                <span>{{ subItem.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>

                        </SidebarMenuItem>
                    </Collapsible>

                </template>

                <!-- 🔹 ITEM SIMPLE -->
                <SidebarMenuItem v-else-if="!item.isGroup && tieneAcceso(item)">
                    <SidebarMenuButton
                        as-child
                        :is-active="urlIsActive(item.href, page.url)"
                        :tooltip="item.title"
                        :class="{ 'bg-primary/10 text-primary': urlIsActive(item.href, page.url) }"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

            </template>

        </SidebarMenu>
    </SidebarGroup>
</template>

<style scoped>
[data-state='open'] > .collapsible-content {
    transition: height 0.25s ease;
}
</style>