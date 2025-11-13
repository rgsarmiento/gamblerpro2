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

defineProps<{
    items: NavItem[]
}>()

const page = usePage()
const user = page.props.auth?.user

// Roles o permisos del usuario actual
const rolesUsuario = computed(() => user?.roles ?? [])
const permisosUsuario = computed(() => user?.permissions ?? [])

// 🔹 Grupos abiertos (persistentes)
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

// 🔹 Alternar apertura de grupo
function toggleGroup(title: string, isOpen: boolean) {
    if (isOpen) {
        if (!openGroups.value.includes(title)) openGroups.value.push(title)
    } else {
        openGroups.value = openGroups.value.filter(t => t !== title)
    }
}

// 🔹 Verifica si el usuario tiene acceso a un ítem
function tieneAcceso(item: NavItem): boolean {
    if (item.roles?.length) {
        return item.roles.some(r => rolesUsuario.value.includes(r))
    }
    if (item.permissions?.length) {
        return item.permissions.some(p => permisosUsuario.value.includes(p))
    }
    return true
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Plataforma</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="String(item.href ?? item.title)">
                <!-- 🔹 Grupo colapsable -->
                <Collapsible
                    v-if="item.isGroup && item.items?.length"
                    as-child
                    :open="openGroups.includes(item.title)"
                    @update:open="(open: boolean) => toggleGroup(item.title, open)"
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton 
                                :tooltip="item.title"
                                :class="{
                                    'opacity-50 cursor-not-allowed': !tieneAcceso(item),
                                }"
                            >
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

                                    <!-- 🔒 Subitem bloqueado -->
                                    <SidebarMenuSubButton
                                        v-else
                                        disabled
                                        class="opacity-50 cursor-not-allowed pointer-events-none"
                                    >
                                        <component :is="subItem.icon" v-if="subItem.icon" />
                                        <span>{{ subItem.title }}</span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- 🔹 Ítem simple -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        v-if="tieneAcceso(item)"
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

                    <!-- 🔒 Ítem bloqueado -->
                    <SidebarMenuButton
                        v-else
                        disabled
                        :tooltip="item.title"
                        class="opacity-50 cursor-not-allowed pointer-events-none"
                    >
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
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

/* Deshabilitar hover visual */
.opacity-50.cursor-not-allowed:hover {
    background-color: transparent !important;
    color: inherit !important;
}

</style>
