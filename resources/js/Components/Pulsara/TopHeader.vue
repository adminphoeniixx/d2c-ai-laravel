<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const syncing = ref(false)
const showMenu = ref(false)

const syncData = () => {
    syncing.value = true
    setTimeout(() => syncing.value = false, 2000)
}

const logout = () => {
    router.post('/logout')
}
</script>

<template>
    <div class="flex justify-between items-center p-8 border-b border-white/5">

        <div>
            <h1 class="text-2xl font-semibold">Operations Dashboard</h1>
            <p class="text-sm text-gray-400">Last synced 2 min ago</p>
        </div>

        <div class="flex items-center gap-6">

            <!-- Sync Button -->
            <button
                @click="syncData"
                class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 flex items-center gap-2"
            >
                <span v-if="!syncing">Sync Now</span>

                <svg v-else class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="white" stroke-width="4" fill="none"/>
                </svg>
            </button>

            <!-- User Dropdown -->
            <div class="relative">
                <div
                    @click="showMenu = !showMenu"
                    class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center cursor-pointer"
                >
                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                </div>

                <div
                    v-if="showMenu"
                    class="absolute right-0 mt-3 w-40 bg-[#111827] border border-white/5 rounded-xl shadow-lg"
                >
                    <button
                        @click="logout"
                        class="w-full text-left px-4 py-3 hover:bg-white/5 text-sm"
                    >
                        Logout
                    </button>
                </div>
            </div>

        </div>

    </div>
</template>