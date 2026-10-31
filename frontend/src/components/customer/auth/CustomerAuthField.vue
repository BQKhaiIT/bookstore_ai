<script setup lang="ts">
const model = defineModel<string>({ required: true })

defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  invalid?: boolean
  error?: string
  readonly?: boolean
  disabled?: boolean
}>()
</script>

<template>
  <div class="ca-field">
    <label class="ca-field__label" :for="id">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      class="ca-field__control"
      :class="{ 'ca-field__control--invalid': invalid }"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :readonly="readonly"
      :disabled="disabled"
      :aria-invalid="invalid || undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
    />
    <p v-if="error" :id="`${id}-error`" class="ca-field__error">{{ error }}</p>
  </div>
</template>
