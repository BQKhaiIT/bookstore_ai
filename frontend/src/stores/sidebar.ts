import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSidebarStore = defineStore('sidebar', () => {
  const visible = ref(true)
  const unfoldable = ref(false)

  function toggleVisible(value?: boolean): void {
    visible.value = value ?? !visible.value
  }

  function toggleUnfoldable(): void {
    unfoldable.value = !unfoldable.value
  }

  return { visible, unfoldable, toggleVisible, toggleUnfoldable }
})
