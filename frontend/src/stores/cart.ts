import { ApiError } from '@/api/errors'
import { addCartItem, fetchCart, removeCartItem, updateCartItem } from '@/api/commerce'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Cart } from '@/types/customer'

export const useCartStore = defineStore('cart', () => {
  const cart = ref<Cart | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const itemCount = computed(() => cart.value?.item_count ?? 0)

  async function load(force = false): Promise<void> {
    if (cart.value && !force) {
      return
    }
    loading.value = true
    error.value = null
    try {
      cart.value = await fetchCart()
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        cart.value = null
        return
      }
      error.value = e instanceof ApiError ? e.message : 'Không tải được giỏ hàng.'
    } finally {
      loading.value = false
    }
  }

  async function add(bookId: number, quantity = 1): Promise<void> {
    loading.value = true
    error.value = null
    try {
      cart.value = await addCartItem(bookId, quantity)
    } catch (e) {
      error.value = e instanceof ApiError ? e.message : 'Không thêm được vào giỏ.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function setQuantity(itemId: number, quantity: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      cart.value = await updateCartItem(itemId, quantity)
    } catch (e) {
      error.value = e instanceof ApiError ? e.message : 'Không cập nhật được số lượng.'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function remove(itemId: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      cart.value = await removeCartItem(itemId)
    } catch (e) {
      error.value = e instanceof ApiError ? e.message : 'Không xóa được sản phẩm.'
      throw e
    } finally {
      loading.value = false
    }
  }

  function clearLocal(): void {
    cart.value = null
  }

  return { cart, loading, error, itemCount, load, add, setQuantity, remove, clearLocal }
})
