import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'
import type {
  MediaDetail,
  MediaListItem,
  MediaUpdatePayload,
  MediaUploadPayload,
  PaginationMeta,
} from '@/types/media'

export const useMediaStore = defineStore('media', () => {
  // ── State ──────────────────────────────────────────────────────────────────

  const items = ref<MediaListItem[]>([])
  const current = ref<MediaDetail | null>(null)
  const meta = ref<PaginationMeta>({
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 20,
  })

  const loading = ref(false)
  const uploading = ref(false)
  const uploadProgress = ref(0)
  const error = ref<string | null>(null)

  // ── Helpers ────────────────────────────────────────────────────────────────

  function setError(message: string | null) {
    error.value = message
  }

  function clearError() {
    error.value = null
  }

  // ── Actions ────────────────────────────────────────────────────────────────

  /**
   * Fetch all media for a project.
   * Optionally filter by type: 'image' | 'video' | 'document' | 'other'
   */
  async function fetchMedia(
    projectId: number,
    params: { type?: string; page?: number; per_page?: number } = {},
  ): Promise<void> {
    loading.value = true
    clearError()

    try {
      const response = await axios.get(`/api/projects/${projectId}/media`, {
        params,
      })

      items.value = response.data.data
      meta.value = response.data.meta
    } catch (err: unknown) {
      const message = extractMessage(err, 'Failed to load media')
      setError(message)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch a single media item by ID.
   */
  async function fetchOne(mediaId: number): Promise<MediaDetail> {
    loading.value = true
    clearError()

    try {
      const response = await axios.get<{ data: MediaDetail }>(
        `/api/media/${mediaId}`,
      )
      current.value = response.data.data
      return response.data.data
    } catch (err: unknown) {
      const message = extractMessage(err, 'Failed to load media item')
      setError(message)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Upload a file to a project.
   * Updates uploadProgress (0–100) during the upload.
   */
  async function upload(
    projectId: number,
    payload: MediaUploadPayload,
  ): Promise<MediaDetail> {
    uploading.value = true
    uploadProgress.value = 0
    clearError()

    const formData = new FormData()
    formData.append('file', payload.file)
    if (payload.name) formData.append('name', payload.name)
    if (payload.extra)
      formData.append('extra', JSON.stringify(payload.extra))

    try {
      const response = await axios.post<{ data: MediaDetail }>(
        `/api/projects/${projectId}/media`,
        formData,
        {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress(event) {
            if (event.total) {
              uploadProgress.value = Math.round(
                (event.loaded / event.total) * 100,
              )
            }
          },
        },
      )

      // Prepend to the local list so the new file appears at the top
      items.value.unshift(response.data.data as unknown as MediaListItem)
      meta.value.total += 1

      return response.data.data
    } catch (err: unknown) {
      const message = extractMessage(err, 'Failed to upload file')
      setError(message)
      throw err
    } finally {
      uploading.value = false
      uploadProgress.value = 0
    }
  }

  /**
   * Update display name / metadata of a media item.
   */
  async function update(
    mediaId: number,
    payload: MediaUpdatePayload,
  ): Promise<MediaDetail> {
    loading.value = true
    clearError()

    try {
      const response = await axios.put<{ data: MediaDetail }>(
        `/api/media/${mediaId}`,
        payload,
      )

      const updated = response.data.data

      // Sync the list item if it exists
      const index = items.value.findIndex((m) => m.id === mediaId)
      if (index !== -1) {
        items.value[index] = {
          ...items.value[index],
          name: updated.name,
          updated_at: updated.updated_at,
        }
      }

      // Sync current if it matches
      if (current.value?.id === mediaId) {
        current.value = updated
      }

      return updated
    } catch (err: unknown) {
      const message = extractMessage(err, 'Failed to update media')
      setError(message)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete a media item by ID.
   */
  async function remove(mediaId: number): Promise<void> {
    loading.value = true
    clearError()

    try {
      await axios.delete(`/api/media/${mediaId}`)

      // Remove from local list
      items.value = items.value.filter((m) => m.id !== mediaId)
      meta.value.total = Math.max(0, meta.value.total - 1)

      // Clear current if it was the deleted item
      if (current.value?.id === mediaId) {
        current.value = null
      }
    } catch (err: unknown) {
      const message = extractMessage(err, 'Failed to delete media')
      setError(message)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Reset all state — call when navigating away from the project.
   */
  function reset() {
    items.value = []
    current.value = null
    meta.value = { total: 0, current_page: 1, last_page: 1, per_page: 20 }
    loading.value = false
    uploading.value = false
    uploadProgress.value = 0
    error.value = null
  }

  // ── Internal helpers ───────────────────────────────────────────────────────

  function extractMessage(err: unknown, fallback: string): string {
    if (
      err &&
      typeof err === 'object' &&
      'response' in err &&
      err.response &&
      typeof err.response === 'object' &&
      'data' in err.response &&
      err.response.data &&
      typeof err.response.data === 'object' &&
      'message' in err.response.data
    ) {
      return String((err.response.data as { message: string }).message)
    }
    return fallback
  }

  return {
    // state
    items,
    current,
    meta,
    loading,
    uploading,
    uploadProgress,
    error,
    // actions
    fetchMedia,
    fetchOne,
    upload,
    update,
    remove,
    reset,
    clearError,
  }
})
