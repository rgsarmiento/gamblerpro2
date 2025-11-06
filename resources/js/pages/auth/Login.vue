<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<!-- <template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="AuthenticatedSessionController.store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                    />
                    Log in
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </Form>
    </AuthBase>
</template> -->
<template>
    <Head title="Iniciar sesión" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-purple-800 via-indigo-800 to-purple-950 relative overflow-hidden">
        <!-- Fondo estrellado -->
        <div class="absolute inset-0 bg-[url('/images/casino-en-linea.webp')] bg-cover opacity-40"></div>

        <!-- Contenedor de login -->
        <div class="relative z-10 w-full max-w-md px-8 py-10 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl shadow-2xl text-white">
            <h1 class="text-3xl font-bold text-center mb-6">Bienvenido</h1>
            <p class="text-center text-sm text-gray-300 mb-8">Inicia sesión para continuar</p>

            <Form
                v-bind="AuthenticatedSessionController.store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-5"
            >
                <!-- Campo Email -->
                <div class="relative">
                    <User class="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        placeholder="Correo electrónico"
                        autocomplete="email"
                        class="pl-10 bg-white/20 border-white/30 text-white placeholder-gray-300 focus:ring-2 focus:ring-purple-400"
                    />
                    <InputError :message="errors.email" />
                </div>

                <!-- Campo Contraseña -->
                <div class="relative">
                    <Lock class="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        placeholder="Contraseña"
                        autocomplete="current-password"
                        class="pl-10 bg-white/20 border-white/30 text-white placeholder-gray-300 focus:ring-2 focus:ring-purple-400"
                    />
                    <InputError :message="errors.password" />
                </div>

                <!-- Recordarme -->
                <div class="flex items-center justify-between text-sm text-gray-200">
                    <Label for="remember" class="flex items-center gap-2 cursor-pointer">
                        <Checkbox id="remember" name="remember" class="border-white/40" />
                        <span>Recordarme</span>
                    </Label>
                </div>

                <!-- Botón -->
                <Button
                    type="submit"
                    class="mt-4 w-full bg-white text-purple-700 font-semibold py-2 rounded-full hover:bg-purple-100 transition-all duration-300"
                    :disabled="processing"
                >
                    <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin mx-auto" />
                    <span v-else>Iniciar sesión</span>
                </Button>
            </Form>
        </div>
    </div>
</template>
