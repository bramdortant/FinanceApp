<script setup>
/**
 * Intermediate step during CSV import: shown when the CSV contains IBANs
 * that don't match any existing account. Lets the user create the missing
 * accounts inline so the import can continue to the preview step.
 *
 * NOTE: This page may be removed in a future phase if we decide the
 * "create account first" workflow is sufficient. See implementation plan
 * Phase 4a notes.
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    token: String,
    missingIbans: Array,
    originalFilename: String,
});

const form = useForm({
    token: props.token,
    accounts: props.missingIbans.map(iban => ({
        iban: iban,
        name: '',
        type: 'checking',
        starting_balance: '0.00',
    })),
});

const submit = () => {
    form.post(route('csv-imports.create-accounts', { token: props.token }));
};

const cancel = () => {
    router.delete(route('csv-imports.cancel', { token: props.token }));
};
</script>

<template>
    <Head title="Ontbrekende rekeningen" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Ontbrekende rekeningen
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="mb-6">
                        <p class="text-gray-700">
                            Het bestand <strong>{{ originalFilename }}</strong> bevat
                            {{ missingIbans.length === 1 ? 'een IBAN die' : 'IBANs die' }}
                            niet bij een bestaande rekening hoort. Maak
                            {{ missingIbans.length === 1 ? 'de rekening' : 'de rekeningen' }}
                            hieronder aan om door te gaan met de import.
                        </p>
                    </div>

                    <form x-on:submit.prevent="submit" class="space-y-8">
                        <div
                            v-for="(account, index) in form.accounts"
                            :key="account.iban"
                            class="rounded-lg border border-gray-200 p-6"
                        >
                            <h3 class="mb-4 text-lg font-medium text-gray-900">
                                {{ account.iban }}
                            </h3>

                            <div class="grid max-w-xl gap-6">
                                <div>
                                    <InputLabel :for="'name-' + index" value="Naam" />
                                    <TextInput
                                        :id="'name-' + index"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="account.name"
                                        required
                                        :autofocus="index === 0"
                                        placeholder="bijv. Rabobank Betaalrekening"
                                    />
                                    <InputError class="mt-2" :message="form.errors['accounts.' + index + '.name']" />
                                </div>

                                <div>
                                    <InputLabel :for="'type-' + index" value="Type" />
                                    <select
                                        :id="'type-' + index"
                                        v-model="account.type"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="checking">Betaalrekening</option>
                                        <option value="savings">Spaarrekening</option>
                                        <option value="cash">Contant</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors['accounts.' + index + '.type']" />
                                </div>

                                <div>
                                    <InputLabel :for="'starting_balance-' + index" value="Beginsaldo" />
                                    <TextInput
                                        :id="'starting_balance-' + index"
                                        type="number"
                                        step="0.01"
                                        min="-9999999.99"
                                        max="9999999.99"
                                        class="mt-1 block w-full"
                                        v-model="account.starting_balance"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors['accounts.' + index + '.starting_balance']" />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Het saldo vóór de eerste transactie in het CSV-bestand.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <InputError class="mt-2" :message="form.errors.accounts" />

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing" x-on:click="submit">
                                Aanmaken en doorgaan
                            </PrimaryButton>
                            <SecondaryButton x-on:click="cancel" :disabled="form.processing">
                                Annuleren
                            </SecondaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
