<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    originalFilename: { type: String, required: true },
    sections: { type: Array, required: true },
    categories: { type: Array, required: true },
    transferCategoryId: { type: Number, default: null },
});

const activeTab = ref(0);
const currentStep = ref('preview'); // 'preview' | 'rules'

// ── Category assignments (hash → category_id) ──────────────────

const categoryAssignments = ref({});

// Track session usage to improve sorting over time within this import.
const sessionUsage = ref({});

const initAssignments = () => {
    const map = {};
    for (const section of props.sections) {
        for (const row of section.rows) {
            if (row.status === 'duplicate' || row.status === 'transfer_mirror') continue;
            if (row.status === 'transfer' && props.transferCategoryId) {
                map[row.hash] = props.transferCategoryId;
            } else if (row.matched_category_id) {
                map[row.hash] = row.matched_category_id;
            }
        }
    }
    categoryAssignments.value = map;
};
initAssignments();

// ── Row helpers ─────────────────────────────────────────────────

const importableRows = computed(() => {
    const rows = [];
    for (const section of props.sections) {
        for (const row of section.rows) {
            if (row.status !== 'duplicate' && row.status !== 'transfer_mirror') {
                rows.push(row);
            }
        }
    }
    return rows;
});

const uncategorizedRows = computed(() =>
    importableRows.value.filter(r => !categoryAssignments.value[r.hash])
);

const allCategorized = computed(() =>
    uncategorizedRows.value.length === 0 && importableRows.value.length > 0
);

const currentSectionRows = computed(() =>
    props.sections[activeTab.value]?.rows ?? []
);

// ── Sidebar categories ──────────────────────────────────────────

const sidebarSearch = ref('');
const sidebarSearchInput = ref(null);

// Determine the category type based on the active row.
const activeCategoryType = computed(() => {
    if (!activeRowHash.value) return 'expense';
    const row = importableRows.value.find(r => r.hash === activeRowHash.value);
    if (!row) return 'expense';
    return parseFloat(row.amount) >= 0 ? 'income' : 'expense';
});

// Categories sorted by usage (backend count + session count), filtered by type and search.
const sortedCategories = computed(() => {
    let cats = props.categories.filter(c => c.type === activeCategoryType.value);

    // Sort by combined usage: backend usage_count + session usage.
    cats = [...cats].sort((a, b) => {
        const usageA = (a.usage_count || 0) + (sessionUsage.value[a.id] || 0);
        const usageB = (b.usage_count || 0) + (sessionUsage.value[b.id] || 0);
        if (usageB !== usageA) return usageB - usageA;
        return a.name.localeCompare(b.name);
    });

    if (sidebarSearch.value) {
        const q = sidebarSearch.value.toLowerCase();
        cats = cats.filter(c => c.name.toLowerCase().includes(q));
    }

    return cats;
});

// ── Active row & navigation ─────────────────────────────────────

const activeRowHash = ref(null);

const goToNextUncategorized = () => {
    if (uncategorizedRows.value.length > 0) {
        activeRowHash.value = uncategorizedRows.value[0].hash;
        scrollActiveRowIntoView();
    } else {
        activeRowHash.value = null;
    }
};

const scrollActiveRowIntoView = () => {
    nextTick(() => {
        const el = document.querySelector(`[data-hash="${activeRowHash.value}"]`);
        if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
};

// ── Paint mode (Ctrl+click) ─────────────────────────────────────

const armedCategoryId = ref(null);
const paintIsDragging = ref(false);

const armedCategoryName = computed(() => {
    if (!armedCategoryId.value) return null;
    const cat = props.categories.find(c => c.id === armedCategoryId.value);
    return cat?.name ?? null;
});

const armCategory = (catId) => {
    armedCategoryId.value = armedCategoryId.value === catId ? null : catId;
};

const paintRow = (row) => {
    if (!armedCategoryId.value) return;
    if (row.status === 'transfer' || row.status === 'duplicate' || row.status === 'transfer_mirror') return;
    categoryAssignments.value[row.hash] = armedCategoryId.value;
    sessionUsage.value[armedCategoryId.value] = (sessionUsage.value[armedCategoryId.value] || 0) + 1;
};

const onRowMouseDown = (row, event) => {
    if (event.ctrlKey && armedCategoryId.value) {
        paintIsDragging.value = true;
        paintRow(row);
        event.preventDefault();
    }
};

const onRowMouseEnter = (row) => {
    if (paintIsDragging.value) {
        paintRow(row);
    }
};

const onMouseUp = () => {
    paintIsDragging.value = false;
};

// ── Category assignment ─────────────────────────────────────────

const assignCategory = (categoryId) => {
    if (!activeRowHash.value) return;
    const row = importableRows.value.find(r => r.hash === activeRowHash.value);
    if (!row || row.status === 'transfer' || row.status === 'duplicate' || row.status === 'transfer_mirror') return;

    categoryAssignments.value[activeRowHash.value] = categoryId;
    sessionUsage.value[categoryId] = (sessionUsage.value[categoryId] || 0) + 1;
    sidebarSearch.value = '';

    // Auto-advance to next uncategorized row.
    nextTick(() => goToNextUncategorized());
};

const onRowClick = (row, event) => {
    // Ctrl+click is handled by onRowMouseDown for paint mode — skip here
    // to avoid firing both paint and select on the same interaction.
    if (event.ctrlKey) return;

    // Normal click = select this row.
    activeRowHash.value = row.hash;
};

// ── Keyboard handling ───────────────────────────────────────────

const handleKeydown = (e) => {
    // ── Rules step keyboard handling ────────────────────────────
    if (currentStep.value === 'rules') {
        // Don't capture keys when typing in pattern inputs.
        if (document.activeElement?.tagName === 'INPUT') return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeRuleIndex.value < ruleProposals.value.length - 1) {
                activeRuleIndex.value++;
            }
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeRuleIndex.value > 0) {
                activeRuleIndex.value--;
            }
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            toggleRule(activeRuleIndex.value);
            if (activeRuleIndex.value < ruleProposals.value.length - 1) {
                activeRuleIndex.value++;
            }
            return;
        }
        if (e.key === ' ') {
            e.preventDefault();
            toggleRule(activeRuleIndex.value);
            return;
        }
        if (e.key === 'Escape') {
            currentStep.value = 'preview';
            return;
        }
        return;
    }

    // ── Preview step keyboard handling ──────────────────────────

    // Don't capture keys when typing in the search input.
    const inSearch = document.activeElement === sidebarSearchInput.value;

    // Number keys assign categories (only when NOT typing in search).
    if (!inSearch && !isNaN(parseInt(e.key))) {
        const num = parseInt(e.key);
        const idx = num === 0 ? 9 : num - 1;
        const cat = sortedCategories.value[idx];
        if (cat) {
            e.preventDefault();
            assignCategory(cat.id);
        }
        return;
    }

    // Tab = jump to next uncategorized row relative to current position.
    if (e.key === 'Tab' && !e.shiftKey) {
        if (uncategorizedRows.value.length > 0) {
            e.preventDefault();
            const allRows = importableRows.value;
            const currentIdx = allRows.findIndex(r => r.hash === activeRowHash.value);
            // Find the next uncategorized row after the current position.
            const next = allRows.find((r, i) =>
                i > currentIdx && !categoryAssignments.value[r.hash]
                && r.status !== 'transfer' && r.status !== 'duplicate' && r.status !== 'transfer_mirror'
            );
            // Wrap around to the first uncategorized if none found after current.
            const target = next || uncategorizedRows.value[0];
            activeRowHash.value = target.hash;
            scrollActiveRowIntoView();
        }
        return;
    }

    // Shift+Tab = jump to previous uncategorized row relative to current position.
    if (e.key === 'Tab' && e.shiftKey) {
        if (uncategorizedRows.value.length > 0) {
            e.preventDefault();
            const allRows = importableRows.value;
            const currentIdx = allRows.findIndex(r => r.hash === activeRowHash.value);
            // Find the previous uncategorized row before the current position.
            let prev = null;
            for (let i = currentIdx - 1; i >= 0; i--) {
                const r = allRows[i];
                if (!categoryAssignments.value[r.hash]
                    && r.status !== 'transfer' && r.status !== 'duplicate' && r.status !== 'transfer_mirror') {
                    prev = r;
                    break;
                }
            }
            // Wrap around to the last uncategorized if none found before current.
            const target = prev || uncategorizedRows.value[uncategorizedRows.value.length - 1];
            activeRowHash.value = target.hash;
            scrollActiveRowIntoView();
        }
        return;
    }

    // Arrow keys navigate all rows.
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        const rows = currentSectionRows.value;
        if (!rows.length) return;
        const currentIdx = rows.findIndex(r => r.hash === activeRowHash.value);
        let nextIdx;
        if (e.key === 'ArrowDown') {
            nextIdx = currentIdx < rows.length - 1 ? currentIdx + 1 : 0;
        } else {
            nextIdx = currentIdx > 0 ? currentIdx - 1 : rows.length - 1;
        }
        activeRowHash.value = rows[nextIdx].hash;
        scrollActiveRowIntoView();
        return;
    }

    // "/" focuses the sidebar search.
    if (e.key === '/' && !inSearch) {
        e.preventDefault();
        sidebarSearchInput.value?.focus();
        return;
    }

    // Escape blurs search if focused, or clears armed category.
    if (e.key === 'Escape') {
        if (inSearch) {
            sidebarSearch.value = '';
            sidebarSearchInput.value?.blur();
        } else {
            armedCategoryId.value = null;
        }
        return;
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('mouseup', onMouseUp);
    goToNextUncategorized();
});
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('mouseup', onMouseUp);
});

// ── Form submission ─────────────────────────────────────────────

const form = useForm({
    token: props.token,
    categories: {},
    rules: [],
});

// ── Rule review (Phase 5b) ─────────────────────────────────────

const ruleProposals = ref([]);
const activeRuleIndex = ref(0);

const goToRuleReview = () => {
    const groups = {};

    for (const row of importableRows.value) {
        if (row.matched_rule_id) continue;
        if (row.status === 'transfer' || row.status === 'duplicate' || row.status === 'transfer_mirror') continue;

        const catId = categoryAssignments.value[row.hash];
        if (!catId) continue;

        const pattern = (row.counterparty_name || row.description || '').trim();
        if (!pattern) continue;

        const key = `${pattern}|||${catId}`;
        if (!groups[key]) {
            const cat = props.categories.find(c => c.id === catId);
            groups[key] = {
                pattern,
                categoryId: catId,
                categoryName: cat?.name ?? '?',
                categoryColor: cat?.color ?? '#6B7280',
                matchCount: 0,
                enabled: true,
            };
        }
        groups[key].matchCount++;
    }

    ruleProposals.value = Object.values(groups)
        .sort((a, b) => b.matchCount - a.matchCount);
    activeRuleIndex.value = 0;

    if (ruleProposals.value.length === 0) {
        submit();
        return;
    }

    currentStep.value = 'rules';
};

const toggleRule = (index) => {
    ruleProposals.value[index].enabled = !ruleProposals.value[index].enabled;
};

const submit = () => {
    form.categories = { ...categoryAssignments.value };
    form.rules = ruleProposals.value
        .filter(p => p.enabled)
        .map(p => ({
            match_pattern: p.pattern,
            category_id: p.categoryId,
        }));
    form.post(route('csv-imports.store'));
};

// ── Formatting helpers ──────────────────────────────────────────

const totals = computed(() => {
    const t = { new: 0, transfer: 0, mirror: 0, duplicate: 0 };
    for (const s of props.sections) {
        t.new += s.summary.new;
        t.transfer += s.summary.transfer;
        t.mirror += s.summary.mirror || 0;
        t.duplicate += s.summary.duplicate;
    }
    return t;
});

const formatCurrency = (amount) =>
    new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(amount);

const formatDate = (date) => {
    if (!date) return '';
    const parts = date.split('-');
    if (parts.length !== 3) return date;
    const [y, m, d] = parts.map(Number);
    const parsed = new Date(y, m - 1, d);
    if (isNaN(parsed.getTime())) return date;
    return new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
};

const statusBadge = (status) => {
    if (status === 'duplicate') return { label: 'Duplicaat', class: 'bg-gray-200 text-gray-700' };
    if (status === 'transfer') return { label: 'Overboeking', class: 'bg-blue-100 text-blue-700' };
    if (status === 'transfer_mirror') return { label: 'Spiegel (skip)', class: 'bg-gray-200 text-gray-500' };
    return { label: 'Nieuw', class: 'bg-green-100 text-green-700' };
};

const getCategoryName = (hash) => {
    const id = categoryAssignments.value[hash];
    if (!id) return null;
    if (id === props.transferCategoryId) return 'Overboeking';
    const cat = props.categories.find(c => c.id === id);
    return cat?.name ?? null;
};

const getCategoryColor = (hash) => {
    const id = categoryAssignments.value[hash];
    if (!id || id === props.transferCategoryId) return '#9CA3AF';
    const cat = props.categories.find(c => c.id === id);
    return cat?.color ?? '#6B7280';
};
</script>

<template>
    <Head title="CSV preview" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                CSV preview
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-[90rem] px-4 sm:px-6 lg:px-8">
                <!-- ═══ RULE REVIEW STEP ═══ -->
                <div v-if="currentStep === 'rules'">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Regels aanmaken</h3>
                            <p class="text-sm text-gray-600">
                                Hieronder staan de patronen die je hebt toegewezen. Vink aan welke je als
                                regel wilt opslaan — bij de volgende import worden ze automatisch toegepast.
                            </p>
                        </div>
                        <SecondaryButton type="button" @click="currentStep = 'preview'">
                            Terug
                        </SecondaryButton>
                    </div>

                    <p class="mb-4 text-xs text-gray-500">
                        <kbd class="rounded bg-gray-200 px-1.5 py-0.5 font-mono">↑</kbd><kbd class="rounded bg-gray-200 px-1.5 py-0.5 font-mono">↓</kbd> navigeren,
                        <kbd class="rounded bg-gray-200 px-1.5 py-0.5 font-mono">Enter</kbd> aan/uit + volgende,
                        <kbd class="rounded bg-gray-200 px-1.5 py-0.5 font-mono">Spatie</kbd> aan/uit,
                        <kbd class="rounded bg-gray-200 px-1.5 py-0.5 font-mono">Esc</kbd> terug
                    </p>

                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-10 px-3 py-2 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Opslaan</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Patroon</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Categorie</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Transacties</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="(proposal, idx) in ruleProposals"
                                    :key="idx"
                                    :class="activeRuleIndex === idx ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-300' : ''"
                                    class="cursor-pointer transition-colors"
                                    @click="activeRuleIndex = idx"
                                >
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="proposal.enabled"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            @change="toggleRule(idx)"
                                            @click.stop
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            v-model="proposal.pattern"
                                            class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            :class="!proposal.enabled ? 'text-gray-400 line-through' : 'text-gray-900'"
                                            @click.stop
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="inline-block h-3 w-3 rounded-full border border-gray-200"
                                                :style="{ backgroundColor: proposal.categoryColor }"
                                            ></span>
                                            <span :class="proposal.enabled ? 'text-gray-700' : 'text-gray-400'">
                                                {{ proposal.categoryName }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right text-sm text-gray-500">
                                        {{ proposal.matchCount }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-sm text-gray-500">
                            {{ ruleProposals.filter(p => p.enabled).length }} van {{ ruleProposals.length }} regels geselecteerd
                        </p>
                        <div class="flex gap-3">
                            <SecondaryButton type="button" @click="currentStep = 'preview'">
                                Terug
                            </SecondaryButton>
                            <PrimaryButton :disabled="form.processing" @click="submit">
                                Bevestig import ({{ totals.new }} nieuw)
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- ═══ PREVIEW STEP ═══ -->
                <div v-else>

                <!-- Summary tiles -->
                <div class="mb-4 grid grid-cols-4 gap-4">
                    <div class="rounded-md bg-green-50 p-3 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ totals.new }}</div>
                        <div class="text-xs text-green-700">Nieuw</div>
                    </div>
                    <div class="rounded-md bg-blue-50 p-3 text-center">
                        <div class="text-2xl font-bold text-blue-700">{{ totals.transfer }}</div>
                        <div class="text-xs text-blue-700">Overboekingen</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-3 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ totals.mirror }}</div>
                        <div class="text-xs text-gray-700">Spiegel (skip)</div>
                    </div>
                    <div class="rounded-md bg-gray-100 p-3 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ totals.duplicate }}</div>
                        <div class="text-xs text-gray-700">Duplicaten (skip)</div>
                    </div>
                </div>

                <!-- Status bar -->
                <div class="mb-4">
                    <div v-if="uncategorizedRows.length > 0" class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                        <strong>{{ uncategorizedRows.length }}</strong> {{ uncategorizedRows.length === 1 ? 'transactie heeft' : 'transacties hebben' }} nog geen categorie.
                        Druk <kbd class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-mono">Tab</kbd> om te beginnen,
                        <kbd class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-mono">1</kbd>–<kbd class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-mono">9</kbd> om toe te wijzen,
                        of <kbd class="rounded bg-amber-200 px-1.5 py-0.5 text-xs font-mono">Ctrl+klik</kbd> om te verven.
                    </div>
                    <div v-else class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">
                        Alle transacties hebben een categorie.
                    </div>
                </div>

                <!-- Account tabs -->
                <div class="mb-4 flex gap-2 border-b border-gray-200">
                    <button
                        v-for="(section, idx) in sections"
                        :key="section.account.id"
                        type="button"
                        class="border-b-2 px-4 py-2 text-sm font-medium transition"
                        :class="activeTab === idx
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="activeTab = idx"
                    >
                        {{ section.account.name }}
                        <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                            {{ section.summary.new }}
                        </span>
                    </button>
                </div>

                <!-- Main content: table + sidebar -->
                <div class="flex gap-4">
                    <!-- Transaction table -->
                    <div class="flex-1 overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Datum</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Omschrijving</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tegenpartij</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Categorie</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Bedrag</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-if="!sections.length || !sections[activeTab]">
                                    <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">
                                        Geen transacties om te tonen.
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="(row, idx) in sections[activeTab].rows"
                                    :key="idx"
                                    :data-hash="row.hash"
                                    :class="[
                                        (row.status === 'duplicate' || row.status === 'transfer_mirror') ? 'opacity-50' : '',
                                        activeRowHash === row.hash ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-300' : '',
                                        (row.status !== 'duplicate' && row.status !== 'transfer_mirror' && !categoryAssignments[row.hash]) ? 'bg-amber-50' : '',
                                    ]"
                                    class="cursor-pointer transition-colors select-none"
                                    @mousedown.stop="onRowMouseDown(row, $event)"
                                    @mouseenter="onRowMouseEnter(row)"
                                    @click="onRowClick(row, $event)"
                                >
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="statusBadge(row.status).class"
                                        >
                                            {{ statusBadge(row.status).label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-700">
                                        {{ formatDate(row.date) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-900">
                                        <div class="truncate max-w-[14rem]" :title="row.description">{{ row.description || '—' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-500">
                                        <div v-if="row.transfer_to_account_name" class="whitespace-nowrap text-blue-700">
                                            ⇄ {{ row.transfer_to_account_name }}
                                        </div>
                                        <div v-else class="truncate max-w-[10rem]" :title="row.counterparty_name || ''">
                                            {{ row.counterparty_name || '—' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm">
                                        <div v-if="row.status === 'duplicate' || row.status === 'transfer_mirror'" class="text-gray-400">
                                            —
                                        </div>
                                        <div v-else-if="categoryAssignments[row.hash]" class="flex items-center gap-1.5">
                                            <span
                                                class="inline-block h-3 w-3 rounded-full border border-gray-200"
                                                :style="{ backgroundColor: getCategoryColor(row.hash) }"
                                            ></span>
                                            <span class="text-gray-700">{{ getCategoryName(row.hash) }}</span>
                                        </div>
                                        <div v-else class="text-amber-600 font-medium">
                                            Kies…
                                        </div>
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-3 py-2 pr-4 text-right text-sm font-semibold"
                                        :class="parseFloat(row.amount) >= 0 ? 'text-green-600' : 'text-red-600'"
                                    >
                                        {{ formatCurrency(row.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Category sidebar -->
                    <div class="w-56 shrink-0">
                        <div class="sticky top-20 rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                            <div class="border-b border-gray-100 p-3">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Categorieën</h3>
                                <input
                                    ref="sidebarSearchInput"
                                    v-model="sidebarSearch"
                                    type="text"
                                    class="mt-2 w-full rounded border-gray-300 px-2 py-1 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Zoek… ( / )"
                                />
                            </div>
                            <div class="max-h-[60vh] overflow-y-auto p-1">
                                <button
                                    v-for="(cat, catIdx) in sortedCategories"
                                    :key="cat.id"
                                    type="button"
                                    class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm transition-colors"
                                    :class="[
                                        armedCategoryId === cat.id
                                            ? 'bg-indigo-100 ring-1 ring-indigo-400 text-indigo-900'
                                            : 'hover:bg-gray-50 text-gray-700',
                                    ]"
                                    @click="assignCategory(cat.id)"
                                    @click.ctrl.prevent="armCategory(cat.id)"
                                    @mousedown.stop
                                >
                                    <span
                                        v-if="catIdx < 10"
                                        class="inline-flex h-4 w-4 items-center justify-center rounded text-xs font-mono text-gray-400 bg-gray-100"
                                    >
                                        {{ catIdx < 9 ? catIdx + 1 : 0 }}
                                    </span>
                                    <span v-else class="inline-block w-4"></span>
                                    <span
                                        class="inline-block h-3 w-3 shrink-0 rounded-full border border-gray-200"
                                        :style="{ backgroundColor: cat.color }"
                                    ></span>
                                    <span class="truncate">{{ cat.name }}</span>
                                </button>
                                <div v-if="sortedCategories.length === 0" class="px-2 py-3 text-center text-xs text-gray-500">
                                    Geen categorieën gevonden.
                                </div>
                            </div>
                            <!-- Armed category indicator for paint mode -->
                            <div v-if="armedCategoryId" class="border-t border-gray-100 px-3 py-2">
                                <p class="text-xs text-indigo-700">
                                    🎨 <strong>{{ armedCategoryName }}</strong>
                                    <br>Ctrl+klik om te verven
                                </p>
                                <button
                                    type="button"
                                    class="mt-1 text-xs text-gray-500 hover:text-gray-700"
                                    @click="armedCategoryId = null"
                                >
                                    Annuleren
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="route('csv-imports.create')">
                        <SecondaryButton type="button">Annuleren</SecondaryButton>
                    </Link>
                    <form @submit.prevent="goToRuleReview">
                        <PrimaryButton :disabled="form.processing || !allCategorized">
                            Bevestig import ({{ totals.new }} nieuw)
                        </PrimaryButton>
                    </form>
                </div>

                </div><!-- end v-else (preview step) -->

                <!-- Errors (visible in both steps) -->
                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">
                    <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
