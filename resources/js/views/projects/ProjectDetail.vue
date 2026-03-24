<script setup lang="ts">
/**
 * ProjectDetail
 *
 * Full project detail view with integrated Media section.
 * The media section provides:
 *   - MediaPicker  → upload new files
 *   - MediaLibrary → browse, rename, delete files
 *
 * The component expects a `projectId` prop (numeric) which is typically
 * resolved from the route params by the parent router view.
 */
import { ref, onMounted, onUnmounted } from 'vue'
import { Paperclip, ChevronDown, ChevronUp, ExternalLink } from 'lucide-vue-next'
import axios from 'axios'
import MediaPicker from '@/components/media/MediaPicker.vue'
import MediaLibrary from '@/components/media/MediaLibrary.vue'
import { useMediaStore } from '@/stores/mediaStore'
import type { MediaDetail, MediaListItem } from '@/types/media'

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
  projectId: number
}>()

// ── Store ─────────────────────────────────────────────────────────────────────

const mediaStore = useMediaStore()

// ── Project data ──────────────────────────────────────────────────────────────

interface ProjectDetail {
  id: number
  name: string
  slug: string
  description: string | null
  cover_image: string | null
  status: {
    value: string
    label: string
    color: string
    badge: string
  }
  visibility: {
    value: string
    label: string
  }
  workspace: {
    id: number
    name: string
    slug: string
  }
  creator: {
    id: number
    name: string
    email: string
  }
  pipelines_count: number
  tasks_count: number
  is_draft: boolean
  is_in_progress: boolean
  is_completed: boolean
  start_date: string | null
  end_date: string | null
  created_at: string
  updated_at: string | null
}

const project = ref<ProjectDetail | null>(null)
const projectLoading = ref(false)
const projectError = ref<string | null>(null)

// ── Media UI state ────────────────────────────────────────────────────────────

const showPicker = ref(false)
const previewItem = ref<MediaListItem | null>(null)

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(async () => {
  await loadProject()
})

onUnmounted(() => {
  mediaStore.reset()
})

// ── Actions ───────────────────────────────────────────────────────────────────

async function loadProject() {
  projectLoading.value = true
  projectError.value = null

  try {
    const response = await axios.get<{ data: ProjectDetail }>(
      `/api/projects/${props.projectId}`,
    )
    project.value = response.data.data
  } catch {
    projectError.value = 'Failed to load project details.'
  } finally {
    projectLoading.value = false
  }
}

function onMediaUploaded(media: MediaDetail) {
  // Library auto-updates via the store; just collapse the picker
  showPicker.value = false
  console.info('Uploaded:', media.name)
}

function onMediaSelect(item: MediaListItem) {
  previewItem.value = item
}

function closePreview() {
  previewItem.value = null
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6 px-4 py-6">
    <!-- Loading state -->
    <div v-if="projectLoading" class="flex items-center justify-center py-24">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- Error state -->
    <div
      v-else-if="projectError"
      class="rounded-lg bg-destructive/10 p-4 text-sm text-destructive"
    >
      {{ projectError }}
    </div>

    <!-- Project content -->
    <template v-else-if="project">
      <!-- ── Project header ─────────────────────────────────────────────────── -->
      <div class="rounded-lg border bg-card p-6 shadow-sm">
        <!-- Cover image -->
        <div
          v-if="project.cover_image"
          class="mb-4 overflow-hidden rounded-md"
        >
          <img
            :src="project.cover_image"
            :alt="project.name"
            class="h-48 w-full object-cover"
          />
        </div>

        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold">{{ project.name }}</h1>
            <p v-if="project.description" class="mt-1 text-sm text-muted-foreground">
              {{ project.description }}
            </p>
          </div>

          <!-- Status badge -->
          <span
            :class="[
              'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
              project.status.badge,
            ]"
          >
            {{ project.status.label }}
          </span>
        </div>

        <!-- Meta row -->
        <div class="mt-4 flex flex-wrap gap-4 text-sm text-muted-foreground">
          <span>
            Workspace:
            <span class="font-medium text-foreground">{{ project.workspace.name }}</span>
          </span>
          <span>
            By:
            <span class="font-medium text-foreground">{{ project.creator.name }}</span>
          </span>
          <span v-if="project.start_date">
            Start: <span class="font-medium text-foreground">{{ project.start_date }}</span>
          </span>
          <span v-if="project.end_date">
            End: <span class="font-medium text-foreground">{{ project.end_date }}</span>
          </span>
          <span>
            Visibility:
            <span class="font-medium text-foreground">{{ project.visibility.label }}</span>
          </span>
        </div>

        <!-- Counts row -->
        <div class="mt-3 flex gap-4 text-sm text-muted-foreground">
          <span>{{ project.pipelines_count ?? 0 }} pipelines</span>
          <span>{{ project.tasks_count ?? 0 }} tasks</span>
          <span>{{ mediaStore.meta.total }} files</span>
        </div>
      </div>

      <!-- ── Media section ──────────────────────────────────────────────────── -->
      <div class="rounded-lg border bg-card shadow-sm">
        <!-- Section header -->
        <div class="flex items-center justify-between border-b px-6 py-4">
          <div class="flex items-center gap-2">
            <Paperclip class="h-5 w-5 text-muted-foreground" />
            <h2 class="font-semibold">Media</h2>
            <span
              v-if="mediaStore.meta.total > 0"
              class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium"
            >
              {{ mediaStore.meta.total }}
            </span>
          </div>

          <!-- Toggle picker button -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-muted"
            @click="showPicker = !showPicker"
          >
            <span>{{ showPicker ? 'Hide uploader' : 'Upload file' }}</span>
            <ChevronUp v-if="showPicker" class="h-4 w-4" />
            <ChevronDown v-else class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-6 p-6">
          <!-- Media Picker (collapsible) -->
          <div v-if="showPicker" class="rounded-lg border bg-muted/20 p-4">
            <h3 class="mb-3 text-sm font-semibold">Upload new file</h3>
            <MediaPicker
              :project-id="project.id"
              @uploaded="onMediaUploaded"
            />
          </div>

          <!-- Media Library -->
          <MediaLibrary
            :project-id="project.id"
            @select="onMediaSelect"
          />
        </div>
      </div>
    </template>

    <!-- ── Media preview dialog ─────────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="previewItem"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @click.self="closePreview"
      >
        <div class="relative w-full max-w-2xl overflow-hidden rounded-xl bg-background shadow-2xl">
          <!-- Dialog header -->
          <div class="flex items-center justify-between border-b px-5 py-4">
            <div class="min-w-0">
              <p class="truncate font-medium">{{ previewItem.name }}</p>
              <p class="mt-0.5 text-xs text-muted-foreground">
                {{ previewItem.human_size }} · {{ previewItem.mime_type }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <a
                :href="previewItem.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
              >
                <ExternalLink class="h-3.5 w-3.5" />
                Open
              </a>
              <button
                type="button"
                class="rounded-md p-1.5 hover:bg-muted"
                @click="closePreview"
              >
                ✕
              </button>
            </div>
          </div>

          <!-- Preview content -->
          <div class="flex items-center justify-center bg-muted/30 p-4">
            <img
              v-if="previewItem.is_image"
              :src="previewItem.url"
              :alt="previewItem.name"
              class="max-h-[60vh] rounded object-contain"
            />
            <video
              v-else-if="previewItem.type === 'video'"
              :src="previewItem.url"
              controls
              class="max-h-[60vh] w-full rounded"
            />
            <div
              v-else
              class="flex flex-col items-center gap-3 py-10 text-muted-foreground"
            >
              <p class="text-sm">Preview not available for this file type.</p>
              <a
                :href="previewItem.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
              >
                <ExternalLink class="h-4 w-4" />
                Download / Open
              </a>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
