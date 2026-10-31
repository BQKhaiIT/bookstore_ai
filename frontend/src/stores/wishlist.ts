import { ApiError } from '@/api/errors'
import {
  addWishlistItem,
  fetchWishlistBookIds,
  removeWishlistItem,
} from '@/api/commerce'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useWishlistStore = defineStore('wishlist', () => {
  const bookIds = ref<Set<number>>(new Set())
  const loaded = ref(false)
  const loading = ref(false)

  async function load(): Promise<void> {
    if (loaded.value) {
      return
    }
    loading.value = true
    try {
      const ids = await fetchWishlistBookIds()
      bookIds.value = new Set(ids)
      loaded.value = true
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        bookIds.value = new Set()
        loaded.value = false
      }
    } finally {
      loading.value = false
    }
  }

  function has(bookId: number): boolean {
    return bookIds.value.has(bookId)
  }

  async function toggle(bookId: number): Promise<boolean> {
    const wasIn = has(bookId)
    if (wasIn) {
      await removeWishlistItem(bookId)
      bookIds.value.delete(bookId)
      return false
    }
    await addWishlistItem(bookId)
    bookIds.value.add(bookId)
    return true
  }

  function clearLocal(): void {
    bookIds.value = new Set()
    loaded.value = false
  }

  return { bookIds, loading, load, has, toggle, clearLocal }
})
