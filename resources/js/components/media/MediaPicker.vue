<script setup lang="ts">
/**
 * MediaPicker
 *
 * Drag-and-drop / click-to-browse file uploader.
 * Uses Shadcn/Vue primitives (Button, Progress, Badge, Card).
 *
 * Emits:
 *   uploaded(media: MediaDetail) — after a successful upload
 */
import { ref, computed } from 'vue'
import { Upload, X, CheckCircle, AlertCircle, FileImage, FileVideo, FileText, File } from 'lucide-vue-next'
import { useMediaStore } from '@/stores/mediaStore'
import type { MediaDetail } from '@/types/media'

// ── Props / Emits ─────────────────────────────────────────────────────────────

const props = defineProps<{
  projectId: number
  /** Accepted MIME types (e.g. 'image/*,application/pdf'). Default: all. */
  accept?: string
  /** Max file size in bytes. Default: 104857600 (100 MB). */
  maxSize?: number
}>()

const emit = defineEmits<{
  uploaded: [media: MediaDetail]
}>()

// ── Store ─────────────────────────────────────────────────────────────────────

const mediaStore = useMediaStore()

// ── Local state ───────────────────────────────────────────────────────────────

const isDragging = ref(false)
const selectedFile = ref<File | null>(null)
const customName = ref('')
const validationError = ref<string | null>(null)
const uploadSuccess = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)

const maxBytes = computed(() => props.maxSize ?? 104_857_600)

// ── File helpers ──────────────────────────────────────────────────────────────

function getFileIcon(file: File) {
  if (file.type.startsWith('image/')) return FileImage
  if (file.type.startsWith('video/')) return FileVideo
  if (
    file.type === 'application/pdf' ||
    file.type.includes('document') ||
    file.type.includes('sheet') ||
    file.type.startsWith('text/')
  )
    return FileText
  return File
}

function humanSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
}

// ── Validation ────────────────────────────────────────────────────────────────

function validate(file: File): string | null {
  if (file.size > maxBytes.value) {
    return `File is too large. Maximum size is ${humanSize(maxBytes.value)}.`
  }
  if (props.accept) {
    const accepted = props.accept.split(',').map((s) => s.trim())
    const ok = accepted.some((pattern) => {
      if (pattern.endsWith('/*')) {
        return file.type.startsWith(pattern.replace('/*', '/'))
      }
      return file.type === pattern
    })
    if (!ok) return `File type "${file.type}" is not allowed.`
  }
  return null
}

// ── Event handlers ────────────────────────────────────────────────────────────

function onDragOver(event: DragEvent) {
  event.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(event: DragEvent) {
  event.preventDefault()
  isDragging.value = false

  const file = event.dataTransfer?.files?.[0]
  if (file) selectFile(file)
}

function onFileInput(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) selectFile(file)
}

function selectFile(file: File) {
  validationError.value = validate(file)
  if (!validationError.value) {
    selectedFile.value = file
    customName.value = file.name
    uploadSuccess.value = false
  }
}

function clearSelection() {
  selectedFile.value = null
  customName.value = ''
  validationError.value = null
  uploadSuccess.value = false
  if (fileInputRef.value) fileInputRef.value.value = ''
}

async function handleUpload() {
  if (!selectedFile.value) return

  uploadSuccess.value = false
  mediaStore.clearError()

  try {
    const media = await mediaStore.upload(props.projectId, {
      file: selectedFile.value,
      name: customName.value || selectedFile.value.name,
    })
    uploadSuccess.value = true
    emit('uploaded', media)
    // Auto-clear after a short delay so the user sees the success state
    setTimeout(clearSelection, 1500)
  } catch {
    // error is shown via mediaStore.error
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- Drop zone -->
    <div
      :class="[
        'relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors',
        isDragging
          ? 'border-primary bg-primary/5'
          : 'border-border hover:border-primary/50 hover:bg-muted/30',
        selectedFile ? 'bg-muted/20' : '',
      ]"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
      @click="fileInputRef?.click()"
    >
      <input
        ref="fileInputRef"
        type="file"
        class="sr-only"
        :accept="accept"
        @change="onFileInput"
      />

      <!-- Empty state -->
      <template v-if="!selectedFile">
        <div class="flex flex-col items-center gap-2 text-center">
          <Upload class="h-10 w-10 text-muted-foreground" />
          <p class="text-sm font-medium">
            Drop a file here, or <span class="text-primary">browse</span>
          </p>
          <p class="text-xs text-muted-foreground">
            {{ accept ? `Accepted: ${accept}` : 'Any file type' }} · Max
            {{ humanSize(maxBytes) }}
          </p>
        </div>
      </template>

      <!-- File selected -->
      <template v-else>
        <div class="flex w-full items-center gap-3" @click.stop>
          <component
            :is="getFileIcon(selectedFile)"
            class="h-8 w-8 shrink-0 text-muted-foreground"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ selectedFile.name }}</p>
            <p class="text-xs text-muted-foreground">
              {{ humanSize(selectedFile.size) }} ·
              {{ selectedFile.type || 'unknown type' }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-md p-1 hover:bg-muted"
            @click.stop="clearSelection"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </template>
    </div>

    <!-- Validation error -->
    <div
      v-if="validationError"
      class="flex items-center gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
    >
      <AlertCircle class="h-4 w-4 shrink-0" />
      {{ validationError }}
    </div>

    <!-- Upload error from store -->
    <div
      v-if="mediaStore.error"
      class="flex items-center gap-2 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
    >
      <AlertCircle class="h-4 w-4 shrink-0" />
      {{ mediaStore.error }}
    </div>

    <!-- Custom name input + upload controls -->
    <template v-if="selectedFile && !uploadSuccess">
      <div class="space-y-2">
        <label class="text-sm font-medium text-foreground">Display name</label>
        <input
          v-model="customName"
          type="text"
          placeholder="Enter a display name (optional)"
          class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
      </div>

      <!-- Upload progress -->
      <div v-if="mediaStore.uploading" class="space-y-1">
        <div class="flex justify-between text-xs text-muted-foreground">
          <span>Uploading…</span>
          <span>{{ mediaStore.uploadProgress }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-secondary">
          <div
            class="h-full rounded-full bg-primary transition-all duration-300"
            :style="{ width: `${mediaStore.uploadProgress}%` }"
          />
        </div>
      </div>

      <button
        type="button"
        :disabled="mediaStore.uploading"
        class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
        @click="handleUpload"
      >
        <Upload class="h-4 w-4" />
        {{ mediaStore.uploading ? 'Uploading…' : 'Upload' }}
      </button>
    </template>

    <!-- Success state -->
    <div
      v-if="uploadSuccess"
      class="flex items-center justify-center gap-2 rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700"
    >
      <CheckCircle class="h-4 w-4" />
      File uploaded successfully!
    </div>
  </div>
</template>
