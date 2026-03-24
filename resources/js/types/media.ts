// ── Media types ───────────────────────────────────────────────────────────────

export type MediaType = 'image' | 'video' | 'document' | 'other'

export interface MediaCreator {
  id: number
  name: string
  email?: string
}

export interface MediaProject {
  id: number
  name: string
  slug: string
}

/**
 * Shape returned by the List endpoint (GET /projects/{project}/media).
 */
export interface MediaListItem {
  id: number
  name: string
  original_name: string
  url: string
  mime_type: string
  type: MediaType
  size: number
  human_size: string
  is_image: boolean
  creator?: MediaCreator
  created_at: string
  updated_at: string | null
}

/**
 * Shape returned by the Detail endpoint (GET /media/{media}).
 */
export interface MediaDetail extends MediaListItem {
  path: string
  disk: string
  is_video: boolean
  is_document: boolean
  extra: Record<string, unknown> | null
  project?: MediaProject
  creator?: MediaCreator & { email: string }
}

// ── API payload shapes ────────────────────────────────────────────────────────

export interface MediaUploadPayload {
  file: File
  name?: string
  extra?: Record<string, unknown>
}

export interface MediaUpdatePayload {
  name: string
  extra?: Record<string, unknown>
}

// ── Pagination meta ───────────────────────────────────────────────────────────

export interface PaginationMeta {
  total: number
  current_page: number
  last_page: number
  per_page: number
}

// ── API response wrappers ─────────────────────────────────────────────────────

export interface ApiSuccessResponse<T> {
  status: 'success'
  message: string
  data: T
}

export interface MediaListResponse {
  status: 'success'
  message: string
  data: MediaListItem[]
  meta: PaginationMeta
}
