<script setup lang="ts">
import { matchesSearch } from '@/utils/searchText'
import { normalizeSelectId } from '@/utils/selectOptions'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

export interface SearchableSelectOption {
  value: string
  label: string
}

const props = withDefaults(
  defineProps<{
    options: SearchableSelectOption[]
    multiple?: boolean
    placeholder?: string
    inputId?: string
    invalid?: boolean
    disabled?: boolean
    loading?: boolean
    emptyText?: string
    errorText?: string
    extraLabels?: Record<string, string>
    missingIds?: string[]
    resolvingLabels?: boolean
  }>(),
  {
    multiple: false,
    placeholder: 'Tìm kiếm...',
    emptyText: 'Không có kết quả',
    errorText: '',
    extraLabels: () => ({}),
    missingIds: () => [],
    resolvingLabels: false,
  },
)

const model = defineModel<string | string[]>({ required: true })

const rootRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const listRef = ref<HTMLElement | null>(null)
const open = ref(false)
const query = ref('')
const highlightIndex = ref(0)

const selectedValues = computed((): string[] => {
  if (props.multiple) {
    const raw = Array.isArray(model.value) ? model.value : []
    return raw.map((v) => normalizeSelectId(v))
  }
  const v = model.value
  if (v === '' || v == null) return []
  return [normalizeSelectId(v as string | number)]
})

const optionMap = computed(
  () => new Map(props.options.map((o) => [normalizeSelectId(o.value), o.label])),
)

function labelForValue(value: string): string {
  const key = normalizeSelectId(value)
  const fromOptions = optionMap.value.get(key)
  if (fromOptions) return fromOptions
  const extra = props.extraLabels?.[key]
  if (extra) return extra
  if (props.missingIds.includes(key)) return `Không tồn tại (ID ${key})`
  if (props.resolvingLabels) return 'Đang tải...'
  return 'Đang tải...'
}

const selectedChips = computed(() =>
  selectedValues.value.map((value) => ({
    value,
    label: labelForValue(value),
    missing: props.missingIds.includes(value),
    pending: !optionMap.value.has(value) && !props.extraLabels?.[value] && !props.missingIds.includes(value),
  })),
)

const filteredOptions = computed(() => {
  const selected = new Set(selectedValues.value)
  return props.options.filter((opt) => {
    const value = normalizeSelectId(opt.value)
    if (props.multiple && selected.has(value)) return false
    return matchesSearch(opt.label, query.value)
  })
})

const singleDisplayLabel = computed(() => {
  if (props.multiple || !selectedValues.value[0]) return ''
  return labelForValue(selectedValues.value[0])
})

const inputPlaceholder = computed(() => {
  if (props.multiple && selectedChips.value.length) return 'Thêm...'
  return props.placeholder
})

const inputValue = computed({
  get(): string {
    if (!props.multiple && !open.value && singleDisplayLabel.value) return singleDisplayLabel.value
    return query.value
  },
  set(v: string): void {
    query.value = v
  },
})

const showEmpty = computed(
  () => !props.loading && open.value && filteredOptions.value.length === 0 && query.value.trim() !== '',
)

function syncHighlight(): void {
  highlightIndex.value = filteredOptions.value.length ? 0 : -1
}

function openDropdown(): void {
  if (props.disabled) return
  open.value = true
  syncHighlight()
}

function closeDropdown(): void {
  open.value = false
  query.value = ''
  highlightIndex.value = -1
}

function toggleDropdown(): void {
  if (open.value) closeDropdown()
  else {
    openDropdown()
    void nextTick(() => inputRef.value?.focus())
  }
}

function selectOption(value: string): void {
  const normalized = normalizeSelectId(value)
  if (props.multiple) {
    const current = Array.isArray(model.value) ? model.value.map((v) => normalizeSelectId(v)) : []
    if (!current.includes(normalized)) model.value = [...current, normalized]
    query.value = ''
    syncHighlight()
    void nextTick(() => inputRef.value?.focus())
  } else {
    model.value = normalized
    closeDropdown()
  }
}

function removeValue(value: string): void {
  const normalized = normalizeSelectId(value)
  if (props.multiple) {
    const current = Array.isArray(model.value) ? model.value : []
    model.value = current.map((v) => normalizeSelectId(v)).filter((v) => v !== normalized)
  } else {
    model.value = ''
  }
}

function onInputKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeDropdown()
    return
  }
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!open.value) openDropdown()
    if (!filteredOptions.value.length) return
    highlightIndex.value = (highlightIndex.value + 1) % filteredOptions.value.length
    scrollHighlightIntoView()
    return
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!open.value) openDropdown()
    if (!filteredOptions.value.length) return
    const len = filteredOptions.value.length
    highlightIndex.value = (highlightIndex.value - 1 + len) % len
    scrollHighlightIntoView()
    return
  }
  if (event.key === 'Enter') {
    if (!open.value) {
      openDropdown()
      return
    }
    event.preventDefault()
    const opt = filteredOptions.value[highlightIndex.value]
    if (opt) selectOption(opt.value)
    return
  }
  if (event.key === 'Backspace' && props.multiple && !query.value && selectedValues.value.length) {
    const last = selectedValues.value[selectedValues.value.length - 1]
    if (last) removeValue(last)
  }
}

function scrollHighlightIntoView(): void {
  void nextTick(() => {
    const el = listRef.value?.querySelector<HTMLElement>('[data-active="true"]')
    el?.scrollIntoView({ block: 'nearest' })
  })
}

function onDocumentPointerDown(event: MouseEvent): void {
  const target = event.target as Node | null
  if (target && rootRef.value && !rootRef.value.contains(target)) closeDropdown()
}

watch(open, (isOpen) => {
  if (isOpen) {
    document.addEventListener('mousedown', onDocumentPointerDown)
    syncHighlight()
  } else {
    document.removeEventListener('mousedown', onDocumentPointerDown)
  }
})

watch(
  () => props.options.length,
  () => {
    if (open.value) syncHighlight()
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentPointerDown)
})
</script>

<template>
  <div
    ref="rootRef"
    class="searchable-select"
    :class="{ 'searchable-select--open': open, 'searchable-select--disabled': disabled }"
  >
    <div
      class="searchable-select__control form-control d-flex flex-wrap align-items-center gap-1"
      :class="{ 'is-invalid': invalid }"
      role="combobox"
      :aria-expanded="open"
      :aria-controls="inputId ? `${inputId}-listbox` : undefined"
      :aria-invalid="invalid || undefined"
      @click="toggleDropdown"
    >
      <template v-if="multiple">
        <CBadge
          v-for="chip in selectedChips"
          :key="chip.value"
          :color="chip.missing ? 'danger' : chip.pending ? 'secondary' : 'primary'"
          shape="rounded-pill"
          class="searchable-select__chip d-inline-flex align-items-center gap-1"
        >
          <span class="searchable-select__chip-label">{{ chip.label }}</span>
          <button
            type="button"
            class="searchable-select__chip-remove btn-close btn-close-white"
            :aria-label="`Bỏ ${chip.label}`"
            @click.stop="removeValue(chip.value)"
          />
        </CBadge>
      </template>
      <input
        :id="inputId"
        ref="inputRef"
        v-model="inputValue"
        type="text"
        class="searchable-select__input border-0 flex-grow-1"
        :placeholder="inputPlaceholder"
        :disabled="disabled"
        autocomplete="off"
        @focus="openDropdown"
        @keydown="onInputKeydown"
        @click.stop
      />
      <button
        v-if="!multiple && selectedValues.length && !disabled"
        type="button"
        class="btn btn-sm btn-link text-body-secondary p-0 searchable-select__clear"
        aria-label="Bỏ lựa chọn"
        @click.stop="removeValue(selectedValues[0]!)"
      >
        ×
      </button>
      <span v-if="loading" class="searchable-select__spinner ms-auto"><CSpinner size="sm" /></span>
      <span v-else class="searchable-select__caret ms-auto" aria-hidden="true">▾</span>
    </div>

    <div v-if="errorText" class="invalid-feedback d-block">{{ errorText }}</div>

    <div v-show="open" class="searchable-select__dropdown shadow-sm">
      <div v-if="loading" class="searchable-select__message px-3 py-2 text-body-secondary">
        <CSpinner size="sm" class="me-2" />Đang tải...
      </div>
      <ul
        v-else
        :id="inputId ? `${inputId}-listbox` : undefined"
        ref="listRef"
        role="listbox"
        class="searchable-select__list list-unstyled mb-0"
        :aria-multiselectable="multiple || undefined"
      >
        <li
          v-for="(opt, index) in filteredOptions"
          :key="opt.value"
          role="option"
          :data-active="index === highlightIndex"
          class="searchable-select__option"
          :class="{ 'searchable-select__option--active': index === highlightIndex }"
          :aria-selected="selectedValues.includes(opt.value)"
          @mousedown.prevent="selectOption(opt.value)"
          @mouseenter="highlightIndex = index"
        >
          {{ opt.label }}
        </li>
        <li v-if="showEmpty" class="searchable-select__message px-3 py-2 text-body-secondary">{{ emptyText }}</li>
        <li
          v-else-if="!filteredOptions.length && !multiple && !query"
          class="searchable-select__message px-3 py-2 text-body-secondary"
        >
          Gõ để tìm kiếm
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.searchable-select {
  position: relative;
}

.searchable-select__control {
  min-height: calc(1.5em + 0.75rem + 2px);
  cursor: text;
  padding-top: 0.25rem;
  padding-bottom: 0.25rem;
}

.searchable-select--disabled .searchable-select__control {
  cursor: not-allowed;
  opacity: 0.65;
}

.searchable-select__input {
  min-width: 6rem;
  padding: 0.125rem 0.25rem;
  background: transparent;
  outline: none;
}

.searchable-select__input:disabled {
  cursor: not-allowed;
}

.searchable-select__chip {
  max-width: 100%;
  font-weight: 500;
}

.searchable-select__chip-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 14rem;
}

.searchable-select__chip-remove {
  --cui-btn-close-width: 0.65em;
  --cui-btn-close-height: 0.65em;
  flex-shrink: 0;
  opacity: 0.85;
}

.searchable-select__caret {
  color: var(--cui-secondary-color);
  font-size: 0.75rem;
  pointer-events: none;
}

.searchable-select__dropdown {
  position: absolute;
  z-index: 1060;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  background: var(--cui-body-bg, #fff);
  border: 1px solid var(--cui-border-color, #b1b7c1);
  border-radius: var(--cui-border-radius, 0.375rem);
  overflow: hidden;
}

.searchable-select__list {
  max-height: 14rem;
  overflow-y: auto;
}

.searchable-select__option {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
}

.searchable-select__option:hover,
.searchable-select__option--active {
  background: var(--cui-tertiary-bg, #f8f9fa);
}

.searchable-select__message {
  font-size: 0.875rem;
}
</style>
