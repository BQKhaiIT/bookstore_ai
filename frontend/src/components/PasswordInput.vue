<script setup lang="ts">
import { computed, ref } from 'vue'

const model = defineModel<string>({ required: true })

defineProps<{
  id: string
  label: string
  autocomplete?: string
  invalid?: boolean
  feedback?: string
}>()

const visible = ref(false)
const inputType = computed(() => (visible.value ? 'text' : 'password'))
</script>

<template>
  <div>
    <CFormLabel :for="id">{{ label }}</CFormLabel>
    <CInputGroup>
      <CFormInput
        :id="id"
        v-model="model"
        :type="inputType"
        :autocomplete="autocomplete"
        :invalid="invalid"
        :aria-describedby="feedback ? `${id}-feedback` : undefined"
      />
      <CInputGroupText>
        <CButton
          type="button"
          color="link"
          class="p-0 link-secondary"
          :aria-label="visible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
          @click="visible = !visible"
        >
          {{ visible ? 'Ẩn' : 'Hiện' }}
        </CButton>
      </CInputGroupText>
    </CInputGroup>
    <CFormFeedback v-if="feedback" :id="`${id}-feedback`" invalid>
      {{ feedback }}
    </CFormFeedback>
  </div>
</template>
