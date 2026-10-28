import { ref } from 'vue'

const visible = ref(false)
const title = ref('Xác nhận')
const message = ref('')
let resolver: ((value: boolean) => void) | null = null

export function useConfirm() {
  function confirm(options: { title?: string; message: string }): Promise<boolean> {
    title.value = options.title ?? 'Xác nhận'
    message.value = options.message
    visible.value = true
    return new Promise((resolve) => {
      resolver = resolve
    })
  }

  function accept(): void {
    visible.value = false
    resolver?.(true)
    resolver = null
  }

  function cancel(): void {
    visible.value = false
    resolver?.(false)
    resolver = null
  }

  return {
    visible,
    title,
    message,
    confirm,
    accept,
    cancel,
  }
}
