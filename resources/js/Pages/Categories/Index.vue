<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    categories: {
        type: Array,
        required: true,
    },
});

const flash = computed(() => usePage().props.flash);

const confirmingDeletion = ref(false);
const categoryToDelete = ref(null);

const confirmDelete = (category) => {
    categoryToDelete.value = category;
    confirmingDeletion.value = true;
};

const deleteCategory = () => {
    router.delete(route('categories.destroy', categoryToDelete.value.id), {
        onSuccess: () => {
            confirmingDeletion.value = false;
            categoryToDelete.value = null;
        },
        onError: () => {
            confirmingDeletion.value = false;
            categoryToDelete.value = null;
        },
    });
};

const closeModal = () => {
    confirmingDeletion.value = false;
    categoryToDelete.value = null;
};
</script>

<template>
    <Head title="Categorieën" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Categorieën
                </h2>
                <Link
                    :href="route('categories.create')"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                >
                    Toevoegen
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="flash?.success"
                    class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash?.error"
                    class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700"
                >
                    {{ flash.error }}
                </div>

                <div v-if="categories.length === 0" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        Nog geen categorieën.
                        <Link :href="route('categories.create')" class="text-indigo-600 hover:text-indigo-500">
                            Maak je eerste categorie aan
                        </Link>
                    </div>
                </div>

                <div v-else class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Kleur
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Naam
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Hoofdcategorie
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Transacties
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Acties
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="category in categories" :key="category.id">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-block h-5 w-5 rounded-full border border-gray-200"
                                        :style="{ backgroundColor: category.color }"
                                    ></span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                    {{ category.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ category.parent?.name || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                    {{ category.transactions_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <Link
                                        :href="route('categories.edit', category.id)"
                                        class="text-sm text-indigo-600 hover:text-indigo-500"
                                    >
                                        Bewerken
                                    </Link>
                                    <button
                                        class="ml-3 text-sm text-red-600 hover:text-red-500"
                                        :class="{ 'opacity-30 cursor-not-allowed': category.transactions_count > 0 }"
                                        :disabled="category.transactions_count > 0"
                                        :title="category.transactions_count > 0 ? 'Kan niet verwijderen: heeft transacties' : 'Categorie verwijderen'"
                                        @click="confirmDelete(category)"
                                    >
                                        Verwijderen
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="confirmingDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Categorie verwijderen
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Weet je zeker dat je "{{ categoryToDelete?.name }}" wilt verwijderen?
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">Annuleren</SecondaryButton>
                    <DangerButton @click="deleteCategory">Verwijderen</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
