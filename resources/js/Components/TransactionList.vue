<script setup>
import { computed } from 'vue';

const props = defineProps({
    transactions: {
        type: Array,
        required: true,
    },
    showAccount: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'Nog geen transacties.',
    },
});

defineEmits(['edit']);

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('nl-NL', {
        style: 'currency',
        currency: 'EUR',
    }).format(amount);
};

const formatDate = (date) => {
    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};

const grouped = computed(() => {
    const groups = {};
    for (const tx of props.transactions) {
        const key = (tx.date || '').split('T')[0];
        if (!groups[key]) groups[key] = [];
        groups[key].push(tx);
    }
    return Object.entries(groups).sort((a, b) => b[0].localeCompare(a[0]));
});
</script>

<template>
    <div v-if="transactions.length === 0" class="p-6 text-center text-sm text-gray-500">
        {{ emptyMessage }}
    </div>
    <div v-else class="divide-y divide-gray-100">
        <div v-for="[date, items] in grouped" :key="date">
            <div class="bg-gray-50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-gray-500">
                {{ formatDate(date) }}
            </div>
            <button
                v-for="tx in items"
                :key="tx.id"
                type="button"
                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-gray-50"
                @click="$emit('edit', tx)"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="inline-block h-3 w-3 flex-shrink-0 rounded-full"
                        :style="{ backgroundColor: tx.category?.color || '#9CA3AF' }"
                        :aria-label="tx.category?.name || 'Geen categorie'"
                    ></span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-gray-900">
                            {{ tx.description || (tx.type === 'transfer' ? 'Overboeking' : '—') }}
                        </div>
                        <div class="truncate text-xs text-gray-500">
                            <template v-if="tx.type === 'transfer'">
                                ⇄ {{ tx.transfer_to_account?.name || tx.account?.name }}
                            </template>
                            <template v-else>
                                {{ tx.category?.name || 'Geen categorie' }}
                            </template>
                            <span v-if="showAccount && tx.account"> · {{ tx.account.name }}</span>
                        </div>
                    </div>
                </div>
                <div
                    class="flex-shrink-0 text-sm font-semibold"
                    :class="parseFloat(tx.display_amount ?? tx.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                    {{ formatCurrency(tx.display_amount ?? tx.amount) }}
                </div>
            </button>
        </div>
    </div>
</template>
