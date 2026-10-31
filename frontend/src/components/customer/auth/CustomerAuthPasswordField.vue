<script setup lang="ts">
import { computed, ref } from 'vue'

const model = defineModel<string>({ required: true })

defineProps<{
  id: string
  label: string
  autocomplete?: string
  invalid?: boolean
  error?: string
}>()

const visible = ref(false)
const inputType = computed(() => (visible.value ? 'text' : 'password'))
</script>

<template>
  <div class="ca-field">
    <label class="ca-field__label" :for="id">{{ label }}</label>
    <div class="ca-password">
      <input
        :id="id"
        v-model="model"
        class="ca-field__control"
        :class="{ 'ca-field__control--invalid': invalid }"
        :type="inputType"
        :autocomplete="autocomplete"
        :aria-invalid="invalid || undefined"
        :aria-describedby="error ? `${id}-error` : undefined"
      />
      <button
        type="button"
        class="ca-password__toggle"
        :aria-label="visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
        @click="visible = !visible"
      >
        {{ visible ? 'Ẩn' : 'Hiện' }}
      </button>
    </div>
    <p v-if="error" :id="`${id}-error`" class="ca-field__error">{{ error }}</p>
  </div>
</template>
