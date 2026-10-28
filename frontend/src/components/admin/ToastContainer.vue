<script setup lang="ts">
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()
</script>

<template>
  <div class="toast-container position-fixed top-0 end-0 p-3">
    <CToast
      v-for="item in toast.items"
      :key="item.id"
      :visible="true"
      :color="item.color"
      autohide
      @close="toast.remove(item.id)"
    >
      <CToastBody>{{ item.message }}</CToastBody>
    </CToast>
  </div>
</template>
