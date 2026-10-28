import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastColor = 'success' | 'danger' | 'warning' | 'info'

export interface ToastItem {
  id: number
  message: string
  color: ToastColor
}

let nextId = 1

export const useToastStore = defineStore('toast', () => {
  const items = ref<ToastItem[]>([])

  function push(message: string, color: ToastColor = 'success'): void {
    const id = nextId++
    items.value.push({ id, message, color })
    window.setTimeout(() => remove(id), 4000)
  }

  function remove(id: number): void {
    items.value = items.value.filter((t) => t.id !== id)
  }

  function success(message: string): void {
    push(message, 'success')
  }

  function error(message: string): void {
    push(message, 'danger')
  }

  return { items, push, remove, success, error }
})
