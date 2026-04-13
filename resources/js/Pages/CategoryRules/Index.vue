<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    rules: { type: Array, required: true },
    conflicts: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const flash = computed(() => usePage().props.flash);

// Edit modal state.
const editModalOpen = ref(false);
const editingRule = ref(null);
const editForm = useForm({
    match_pattern: '',
    category_id: null,
});

const openEdit = (rule) => {
    editingRule.value = rule;
    editForm.match_pattern = rule.match_pattern;
    editForm.category_id = rule.category_id;
    editModalOpen.value = true;
};

const submitEdit = () => {
    editForm.put(route('category-rules.update', editingRule.value.id), {
        onSuccess: () => {
            editModalOpen.value = false;
            editingRule.value = null;
        },
    });
};

const closeEdit = () => {
    editModalOpen.value = false;
    editingRule.value = null;
    editForm.reset();
};

// Create modal state.
const createModalOpen = ref(false);
const createForm = useForm({
    match_pattern: '',
    category_id: null,
});

const closeCreate = () => {
    createModalOpen.value = false;
    createForm.reset();
    createForm.clearErrors();
};

const submitCreate = () => {
    createForm.post(route('category-rules.store'), {
        onSuccess: () => closeCreate(),
    });
};

// Delete.
const confirmingDeletion = ref(false);
const ruleToDelete = ref(null);

const confirmDelete = (rule) => {
    ruleToDelete.value = rule;
    confirmingDeletion.value = true;
};

const deleteRule = () => {
    router.delete(route('category-rules.destroy', ruleToDelete.value.id), {
        onSuccess: () => {
            confirmingDeletion.value = false;
            ruleToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Categorieregels" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Categorieregels
                </h2>
                <button
                    type="button"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                    @click="createModalOpen = true"
                >
                    Toevoegen
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    v-if="flash?.success"
                    class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700"
                    role="alert"
                >
                    {{ flash.success }}
                </div>

                <p class="mb-4 text-sm text-gray-600">
                    Regels worden automatisch toegepast bij CSV-import. Als een omschrijving
                    het patroon bevat, wordt de bijbehorende categorie toegewezen. Bij
                    meerdere matches wint het langste patroon.
                </p>

                <div v-if="rules.length === 0" class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-center text-gray-500">
                        Nog geen regels. Regels worden aangemaakt tijdens het importeren van CSV-bestanden.
                    </div>
                </div>

                <div v-else class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Patroon
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Categorie
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Matches
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Acties
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="rule in rules" :key="rule.id">
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ rule.match_pattern }}</code>
                                    <span
                                        v-if="conflicts[rule.id]"
                                        class="ml-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700"
                                        title="Dit patroon overlapt met een ander patroon dat een andere categorie heeft"
                                    >
                                        ⚠ overlap
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="inline-block h-3 w-3 rounded-full border border-gray-200"
                                            :style="{ backgroundColor: rule.category?.color }"
                                        ></span>
                                        <span class="text-gray-700">{{ rule.category?.name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                    {{ rule.match_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <button
                                        class="text-sm text-indigo-600 hover:text-indigo-500"
                                        @click="openEdit(rule)"
                                    >
                                        Bewerken
                                    </button>
                                    <button
                                        class="ml-3 text-sm text-red-600 hover:text-red-500"
                                        @click="confirmDelete(rule)"
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

        <!-- Edit modal -->
        <Modal :show="editModalOpen" @close="closeEdit">
            <form class="p-6" @submit.prevent="submitEdit">
                <h2 class="text-lg font-medium text-gray-900">Regel bewerken</h2>

                <div class="mt-4">
                    <InputLabel for="edit-pattern" value="Patroon" />
                    <TextInput
                        id="edit-pattern"
                        v-model="editForm.match_pattern"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        Als de omschrijving of tegenpartij dit patroon bevat, wordt de categorie automatisch toegewezen.
                    </p>
                    <InputError class="mt-2" :message="editForm.errors.match_pattern" />
                </div>

                <div class="mt-4">
                    <InputLabel for="edit-category" value="Categorie" />
                    <select
                        id="edit-category"
                        v-model="editForm.category_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option :value="null" disabled>Kies een categorie…</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="editForm.errors.category_id" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeEdit">Annuleren</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Opslaan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Create modal -->
        <Modal :show="createModalOpen" @close="closeCreate">
            <form class="p-6" @submit.prevent="submitCreate">
                <h2 class="text-lg font-medium text-gray-900">Nieuwe regel</h2>

                <div class="mt-4">
                    <InputLabel for="create-pattern" value="Patroon" />
                    <TextInput
                        id="create-pattern"
                        v-model="createForm.match_pattern"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        placeholder="bijv. Albert Heijn"
                    />
                    <p class="mt-1 text-xs text-gray-500">
                        Als de omschrijving of tegenpartij dit patroon bevat, wordt de categorie automatisch toegewezen.
                    </p>
                    <InputError class="mt-2" :message="createForm.errors.match_pattern" />
                </div>

                <div class="mt-4">
                    <InputLabel for="create-category" value="Categorie" />
                    <select
                        id="create-category"
                        v-model="createForm.category_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option :value="null" disabled>Kies een categorie…</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="createForm.errors.category_id" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeCreate">Annuleren</SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">Aanmaken</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Delete confirmation modal -->
        <Modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Regel verwijderen</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Weet je zeker dat je de regel
                    "<code class="rounded bg-gray-100 px-1 text-xs">{{ ruleToDelete?.match_pattern }}</code>"
                    wilt verwijderen? Bestaande transacties behouden hun categorie.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirmingDeletion = false">Annuleren</SecondaryButton>
                    <DangerButton @click="deleteRule">Verwijderen</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
