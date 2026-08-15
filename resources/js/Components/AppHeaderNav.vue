<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAppNav } from '@/composables/useAppNav';

const emit = defineEmits(['navigate']);

const page = usePage();
const { t } = useI18n();
const { catalog, groupedOverflow } = useAppNav();

const compact = ref(false);
const moreOpen = ref(false);
const visibleCount = ref(0);
const trackRef = ref(null);
const moreButtonRef = ref(null);
const morePanelRef = ref(null);
const measureRef = ref(null);

const visibleItems = computed(() => (compact.value ? [] : catalog.value.slice(0, visibleCount.value)));
const overflowItems = computed(() =>
    compact.value ? catalog.value : catalog.value.slice(visibleCount.value),
);
const overflowGroups = computed(() => groupedOverflow(overflowItems.value));
const moreActive = computed(() => overflowItems.value.some((item) => item.active));
const moreId = 'erp-header-more-panel';

const measure = () => {
    if (compact.value) {
        visibleCount.value = 0;
        return;
    }

    const track = trackRef.value;
    const measureEl = measureRef.value;
    if (!track || !measureEl) {
        return;
    }

    const available = track.clientWidth;
    const measureLinks = [...measureEl.querySelectorAll('[data-nav-measure]')];
    const moreEl = measureEl.querySelector('[data-more-measure]');
    const moreWidth = moreEl?.offsetWidth ?? 108;
    const gap = 4;

    let used = 0;
    let count = 0;

    for (let index = 0; index < measureLinks.length; index += 1) {
        const width = measureLinks[index].offsetWidth;
        const remaining = measureLinks.length - index - 1;
        const reserveMore = remaining > 0 ? moreWidth + gap : 0;

        if (used + width + (count > 0 ? gap : 0) + reserveMore <= available) {
            used += width + (count > 0 ? gap : 0);
            count += 1;
        } else {
            break;
        }
    }

    visibleCount.value = count;
};

const setCompact = () => {
    compact.value = window.matchMedia('(max-width: 991.98px)').matches;
    if (compact.value) {
        moreOpen.value = false;
    }
};

const closeMore = () => {
    moreOpen.value = false;
};

const toggleMore = () => {
    moreOpen.value = !moreOpen.value;
};

watch(
    () => page.url,
    () => closeMore(),
);

watch([moreOpen, compact], ([open, isCompact]) => {
    document.body.style.overflow = open && isCompact ? 'hidden' : '';
});

const onNavigate = () => {
    closeMore();
    emit('navigate');
};

const onDocumentPointer = (event) => {
    if (!moreOpen.value || compact.value) {
        return;
    }

    const target = event.target;
    if (moreButtonRef.value?.contains(target) || morePanelRef.value?.contains(target)) {
        return;
    }

    closeMore();
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && moreOpen.value) {
        closeMore();
        moreButtonRef.value?.focus();
    }
};

let resizeObserver;

onMounted(() => {
    setCompact();
    nextTick(measure);

    resizeObserver = new ResizeObserver(() => {
        measure();
    });

    if (trackRef.value) {
        resizeObserver.observe(trackRef.value);
    }

    window.addEventListener('resize', setCompact);
    document.addEventListener('pointerdown', onDocumentPointer);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    resizeObserver?.disconnect();
    window.removeEventListener('resize', setCompact);
    document.removeEventListener('pointerdown', onDocumentPointer);
    document.removeEventListener('keydown', onKeydown);
});

watch(catalog, () => nextTick(measure));
watch(compact, () => nextTick(measure));
</script>

<template>
    <div class="erp-header-nav" ref="trackRef">
        <div ref="measureRef" class="erp-header-nav-measure" aria-hidden="true">
            <span
                v-for="item in catalog"
                :key="`m-${item.key}`"
                class="erp-header-link"
                data-nav-measure
            >
                {{ item.label }}
            </span>
            <span class="erp-header-more" data-more-measure>
                {{ t('nav.more') }}
                <span class="erp-header-caret" />
            </span>
        </div>

        <nav class="erp-header-links" :aria-label="t('nav.menu')" v-show="!compact">
            <Link
                v-for="item in visibleItems"
                :key="item.key"
                :href="item.href"
                class="erp-header-link"
                :class="{ 'is-active': item.active }"
                :aria-current="item.active ? 'page' : undefined"
                @click="onNavigate"
            >
                {{ item.label }}
            </Link>
        </nav>

        <div v-if="overflowItems.length" class="erp-header-more-wrap" :class="{ 'is-compact': compact }">
            <button
                ref="moreButtonRef"
                type="button"
                class="erp-header-more"
                :class="{ 'is-open': moreOpen, 'is-active': moreActive }"
                :aria-expanded="moreOpen"
                :aria-controls="moreId"
                :aria-haspopup="compact ? 'dialog' : 'menu'"
                :aria-label="compact ? t('nav.menu') : t('nav.more')"
                @click="toggleMore"
            >
                <span class="erp-header-more-label">{{ compact ? t('nav.menu') : t('nav.more') }}</span>
                <span class="erp-header-caret" aria-hidden="true" />
            </button>

            <Transition
                enter-active-class="erp-nav-enter-active"
                enter-from-class="erp-nav-enter-from"
                enter-to-class="erp-nav-enter-to"
                leave-active-class="erp-nav-leave-active"
                leave-from-class="erp-nav-leave-from"
                leave-to-class="erp-nav-leave-to"
            >
                <div
                    v-if="moreOpen && !compact"
                    :id="moreId"
                    ref="morePanelRef"
                    class="erp-header-more-panel"
                    role="menu"
                    :aria-label="t('nav.more')"
                >
                    <template v-for="group in overflowGroups" :key="group.key">
                        <div v-if="group.label" class="erp-header-more-heading">{{ group.label }}</div>
                        <Link
                            v-for="item in group.items"
                            :key="item.key"
                            :href="item.href"
                            class="erp-header-more-item"
                            :class="{ 'is-active': item.active }"
                            role="menuitem"
                            :aria-current="item.active ? 'page' : undefined"
                            @click="onNavigate"
                        >
                            {{ item.label }}
                        </Link>
                    </template>
                </div>
            </Transition>
        </div>

        <Teleport to="body">
            <Transition
                enter-active-class="erp-nav-backdrop-enter-active"
                enter-from-class="erp-nav-backdrop-enter-from"
                enter-to-class="erp-nav-backdrop-enter-to"
                leave-active-class="erp-nav-backdrop-leave-active"
                leave-from-class="erp-nav-backdrop-leave-from"
                leave-to-class="erp-nav-backdrop-leave-to"
            >
                <div
                    v-if="moreOpen && compact"
                    class="erp-mobile-nav-backdrop"
                    @click="closeMore"
                />
            </Transition>
            <Transition
                enter-active-class="erp-mobile-nav-enter-active"
                enter-from-class="erp-mobile-nav-enter-from"
                enter-to-class="erp-mobile-nav-enter-to"
                leave-active-class="erp-mobile-nav-leave-active"
                leave-from-class="erp-mobile-nav-leave-from"
                leave-to-class="erp-mobile-nav-leave-to"
            >
                <nav
                    v-if="moreOpen && compact"
                    :id="moreId"
                    class="erp-mobile-nav"
                    :aria-label="t('nav.menu')"
                >
                    <div class="erp-mobile-nav-head">
                        <strong>{{ t('nav.menu') }}</strong>
                        <button
                            type="button"
                            class="btn btn-erp-ghost erp-icon-btn"
                            :aria-label="t('nav.menu')"
                            @click="closeMore"
                        >
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="erp-mobile-nav-body">
                        <template v-for="group in overflowGroups" :key="`m-${group.key}`">
                            <div v-if="group.label" class="erp-header-more-heading">{{ group.label }}</div>
                            <Link
                                v-for="item in group.items"
                                :key="item.key"
                                :href="item.href"
                                class="erp-mobile-nav-link"
                                :class="{ 'is-active': item.active }"
                                :aria-current="item.active ? 'page' : undefined"
                                @click="onNavigate"
                            >
                                {{ item.label }}
                            </Link>
                        </template>
                    </div>
                </nav>
            </Transition>
        </Teleport>
    </div>
</template>
