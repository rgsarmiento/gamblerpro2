<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { 
    LayoutGrid, 
    NotebookPen, 
    Receipt, 
    Box, 
    Building2,
    ArrowRightLeft,
    Settings,
    Store,
    Monitor,
    Tag
} from 'lucide-vue-next'

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Movimientos',
        icon: ArrowRightLeft,
        isGroup: true,
        items: [
            {
                title: 'Lecturas',
                href: '/lecturas',
                icon: NotebookPen,
            },
            {
                title: 'Gastos',
                href: '/gastos',
                icon: Receipt,
            },
            {
                title: 'Cierres',
                href: '/cierres',
                icon: Box,
            },
        ]
    },
    {
        title: 'Mantenimiento',
        icon: Settings,
        isGroup: true,
        items: [
            {
                title: 'Máquinas',
                href: '/maquinas',
                icon: Monitor,
            },
            {
                title: 'Proveedores',
                href: '/proveedores',
                icon: Building2,
            },
            {
                title: 'Tipos de Gasto',
                href: '/tipos-gasto',
                icon: Tag,
            },
        ]
    },
    {
        title: 'Administrador',
        icon: Settings,
        isGroup: true,
        items: [
            {
                title: 'Casinos',
                href: '/casinos',
                icon: Building2,
            },
            {
                title: 'Sucursales',
                href: '/sucursales',
                icon: Store,
            },
            {
                title: 'Usuarios',
                href: '/usuarios',
                icon: Monitor,
            },            
        ]
    }
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>