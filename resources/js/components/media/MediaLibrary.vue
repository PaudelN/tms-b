<script setup lang="ts">
/**
 * MediaLibrary
 *
 * Displays all media for a project in a responsive grid.
 * Supports inline name editing and delete confirmation.
 * Uses Shadcn/Vue (Card, Badge, Button, Dialog, Input) primitives.
 *
 * Emits:
 *   select(item: MediaListItem) — when user clicks an item (for preview)
 */
import { ref, computed, watch, onMounted } from 'vue'
import {
  FileImage,
  FileVideo,
  FileText,
  File,
  Pencil,
  Trash2,
  Check,
  X,
  ExternalLink,
  Loader2,
  ImageOff,
} from 'lucide-vue-next'
import { useMediaStore } from '@/stores/mediaStore'
import type { MediaListItem } from '@/types/media'

// ── Props / Emits ─────────────────────────────────────────────────────────────

const props = defineProps<{
  projectId: number
}>()

const emit = defineEmits<{
  select: [item: MediaListItem]
}>()

// ── Store ─────────────────────────────────────────────────────────────────────

const mediaStore = useMediaStore()

// ── Local state ───────────────────────────────────────────────────────────────

const typeFilter = ref<string>('all')
const editingId = ref<number | null>(null)
const editName = ref('')
const deleteConfirmId = ref<number | null>(null)
const imgErrors = ref<Set<number>>(new Set())

// ── Computed ──────────────────────────────────────────────────────────────────

const typeOptions = [
  { value: 'all', label: 'All' },
  { value: 'image', label: 'Images' },
  { value: 'video', label: 'Videos' },
  { value: 'document', label: 'Documents' },
  { value: 'other', label: 'Other' },
]

const filteredItems = computed<MediaListItem[]>(() => {
  if (typeFilter.value === 'all') return mediaStore.items
  return mediaStore.items.filter((m) => m.type === typeFilter.value)
})

const isEmpty = computed(() => !mediaStore.loading && filteredItems.value.length === 0)

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => load())

watch(typeFilter, () => {
  if (typeFilter.value === 'all') {
    mediaStore.fetchMedia(props.projectId)
  } else {
    mediaStore.fetchMedia(props.projectId, { type: typeFilter.value })
  }
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function load() {
  mediaStore.fetchMedia(props.projectId)
}

function fileIcon(item: MediaListItem) {
  if (item.type === 'image') return FileImage
  if (item.type === 'video') return FileVideo
  if (item.type === 'document') return FileText
  return File
}

function typeBadgeClass(type: string): string {
  const map: Record<string, string> = {
    image: 'bg-blue-100 text-blue-700',
    video: 'bg-purple-100 text-purple-700',
    document: 'bg-amber-100 text-amber-700',
    other: 'bg-slate-100 text-slate-600',
  }
  return map[type] ?? map.other
}

function onImgError(id: number) {
  imgErrors.value = new Set([...imgErrors.value, id])
}

// ── Inline edit ───────────────────────────────────────────────────────────────

function startEdit(item: MediaListItem) {
  editingId.value = item.id
  editName.value = item.name
}

function cancelEdit() {
  editingId.value = null
  editName.value = ''
}

async function saveEdit(item: MediaListItem) {
  const name = editName.value.trim()
  if (!name || name === item.name) {
    cancelEdit()
    return
  }

  await mediaStore.update(item.id, { name })
  cancelEdit()
}

// ── Delete ────────────────────────────────────────────────────────────────────

function confirmDelete(item: MediaListItem) {
  deleteConfirmId.value = item.id
}

function cancelDelete() {
  deleteConfirmId.value = null
}

async function executeDelete(id: number) {
  await mediaStore.remove(id)
  deleteConfirmId.value = null
}
</script>

<template>
  <div class="space-y-4">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex gap-1 rounded-lg bg-muted p-1">
        <button
          v-for="opt in typeOptions"
          :key="opt.value"
          type="button"
          :class="[
            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
            typeFilter === opt.value
              ? 'bg-background shadow text-foreground'
              : 'text-muted-foreground hover:text-foreground',
          ]"
          @click="typeFilter = opt.value"
        >
          {{ opt.label }}
        </button>
      </div>

      <p class="text-sm text-muted-foreground">
        {{ mediaStore.meta.total }} file{{ mediaStore.meta.total !== 1 ? 's' : '' }}
      </p>
    </div>

    <!-- Error banner -->
    <div
      v-if="mediaStore.error"
      class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
    >
      {{ mediaStore.error }}
    </div>

    <!-- Loading skeleton -->
    <div v-if="mediaStore.loading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      <div
        v-for="i in 8"
        :key="i"
        class="animate-pulse rounded-lg bg-muted aspect-square"
      />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="isEmpty"
      class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center"
    >
      <FileImage class="mb-3 h-12 w-12 text-muted-foreground/40" />
      <p class="font-medium text-muted-foreground">No files yet</p>
      <p class="mt-1 text-sm text-muted-foreground/70">
        Upload files using the picker above.
      </p>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      <div
        v-for="item in filteredItems"
        :key="item.id"
        class="group relative overflow-hidden rounded-lg border bg-card shadow-sm transition-shadow hover:shadow-md"
      >
        <!-- Delete confirmation overlay -->
        <div
          v-if="deleteConfirmId === item.id"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-background/95 p-4 text-center"
        >
          <Trash2 class="h-8 w-8 text-destructive" />
          <p class="text-sm font-medium">Delete this file?</p>
          <p class="text-xs text-muted-foreground">This action cannot be undone.</p>
          <div class="flex gap-2">
            <button
              type="button"
              class="rounded-md bg-destructive px-3 py-1.5 text-xs font-medium text-destructive-foreground hover:bg-destructive/90 disabled:opacity-50"
              :disabled="mediaStore.loading"
              @click="executeDelete(item.id)"
            >
              <Loader2 v-if="mediaStore.loading" class="h-3 w-3 animate-spin" />
              <span v-else>Delete</span>
            </button>
            <button
              type="button"
              class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-muted"
              @click="cancelDelete"
            >
              Cancel
            </button>
          </div>
        </div>

        <!-- Thumbnail -->
        <div
          class="relative aspect-square cursor-pointer bg-muted"
          @click="emit('select', item)"
        >
          <img
            v-if="item.is_image && !imgErrors.has(item.id)"
            :src="item.url"
            :alt="item.name"
            class="h-full w-full object-cover"
            @error="onImgError(item.id)"
          />
          <div
            v-else
            class="flex h-full w-full items-center justify-center"
          >
            <component :is="item.is_image && imgErrors.has(item.id) ? ImageOff : fileIcon(item)" class="h-10 w-10 text-muted-foreground/50" />
          </div>

          <!-- Quick-action overlay (shown on hover) -->
          <div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
            <a
              :href="item.url"
              target="_blank"
              rel="noopener noreferrer"
              class="rounded-md bg-white/90 p-1.5 text-foreground hover:bg-white"
              title="Open in new tab"
              @click.stop
            >
              <ExternalLink class="h-4 w-4" />
            </a>
            <button
              type="button"
              class="rounded-md bg-white/90 p-1.5 text-foreground hover:bg-white"
              title="Rename"
              @click.stop="startEdit(item)"
            >
              <Pencil class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-md bg-white/90 p-1.5 text-destructive hover:bg-white"
              title="Delete"
              @click.stop="confirmDelete(item)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-2">
          <!-- Inline edit mode -->
          <div v-if="editingId === item.id" class="flex items-center gap-1">
            <input
              v-model="editName"
              type="text"
              class="min-w-0 flex-1 rounded border border-input bg-background px-2 py-1 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              @keydown.enter="saveEdit(item)"
              @keydown.escape="cancelEdit"
            />
            <button
              type="button"
              class="rounded p-1 text-green-600 hover:bg-green-50"
              @click="saveEdit(item)"
            >
              <Check class="h-3 w-3" />
            </button>
            <button
              type="button"
              class="rounded p-1 text-muted-foreground hover:bg-muted"
              @click="cancelEdit"
            >
              <X class="h-3 w-3" />
            </button>
          </div>

          <!-- Normal display mode -->
          <template v-else>
            <p class="truncate text-xs font-medium" :title="item.name">
              {{ item.name }}
            </p>
            <div class="mt-1 flex items-center justify-between">
              <span
                :class="['rounded px-1.5 py-0.5 text-[10px] font-medium capitalize', typeBadgeClass(item.type)]"
              >
                {{ item.type }}
              </span>
              <span class="text-[10px] text-muted-foreground">{{ item.human_size }}</span>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Load more -->
    <div
      v-if="mediaStore.meta.current_page < mediaStore.meta.last_page"
      class="flex justify-center pt-2"
    >
      <button
        type="button"
        class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
        :disabled="mediaStore.loading"
        @click="mediaStore.fetchMedia(projectId, { page: mediaStore.meta.current_page + 1 })"
      >
        Load more
      </button>
    </div>
  </div>
</template>
