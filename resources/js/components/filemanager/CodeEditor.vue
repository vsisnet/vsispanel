<template>
  <div class="flex flex-col h-full">
    <!-- Tab Bar -->
    <div v-if="tabs.length > 0" class="flex items-center bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
      <div v-for="tab in tabs" :key="tab.path"
        :class="['flex items-center gap-1.5 px-3 py-2 text-xs cursor-pointer border-r border-gray-200 dark:border-gray-700 select-none transition-colors min-w-0',
          activeTabPath === tab.path
            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white'
            : 'text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800/50']"
        @click="switchTab(tab.path)">
        <span class="truncate max-w-[120px]">{{ tab.name }}</span>
        <span v-if="tab.modified" class="w-2 h-2 rounded-full bg-orange-400 flex-shrink-0" :title="$t('fileManager.unsaved')"></span>
        <button @click.stop="closeTab(tab.path)" class="ml-1 text-gray-400 hover:text-red-500 flex-shrink-0">
          <XMarkIcon class="w-3 h-3" />
        </button>
      </div>
    </div>

    <!-- Editor Area -->
    <div ref="editorContainer" class="flex-1 min-h-0"></div>

    <!-- Status Bar -->
    <div v-if="activeTab" class="flex items-center justify-between px-3 py-1 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
      <div class="flex items-center gap-3">
        <span>{{ activeTab.syntax }}</span>
        <span v-if="cursorPosition">{{ $t('fileManager.line') }} {{ cursorPosition.line }}, {{ $t('fileManager.col') }} {{ cursorPosition.col }}</span>
      </div>
      <div class="flex items-center gap-3">
        <span v-if="activeTab.modified" class="text-orange-500">{{ $t('fileManager.modified') }}</span>
        <span>{{ formatSize(activeTab.size) }}</span>
        <button @click="saveCurrentFile" class="px-2 py-0.5 bg-primary-600 text-white rounded text-xs hover:bg-primary-700"
          :disabled="!activeTab.modified">
          {{ $t('common.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter } from '@codemirror/view'
import { EditorState } from '@codemirror/state'
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands'
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search'
import { foldGutter, indentOnInput, bracketMatching, syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language'
import { html } from '@codemirror/lang-html'
import { css } from '@codemirror/lang-css'
import { javascript } from '@codemirror/lang-javascript'
import { json } from '@codemirror/lang-json'
import { xml } from '@codemirror/lang-xml'
import { markdown } from '@codemirror/lang-markdown'
import { php } from '@codemirror/lang-php'
import { sql } from '@codemirror/lang-sql'
import { oneDark } from '@codemirror/theme-one-dark'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const { t } = useI18n()

const props = defineProps({
  domainId: { type: String, required: true },
  darkMode: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'close'])

const editorContainer = ref(null)
const tabs = ref([])
const activeTabPath = ref(null)
const cursorPosition = ref({ line: 1, col: 1 })
let editorView = shallowRef(null)

const activeTab = ref(null)

watch(activeTabPath, (path) => {
  activeTab.value = tabs.value.find(t => t.path === path) || null
})

function getLanguageExtension(syntax) {
  switch (syntax) {
    case 'html': return html()
    case 'css': return css()
    case 'javascript': return javascript()
    case 'json': return json()
    case 'xml': return xml()
    case 'markdown': return markdown()
    case 'php': return php()
    case 'sql': return sql()
    default: return []
  }
}

function createEditorState(content, syntax) {
  const extensions = [
    lineNumbers(),
    highlightActiveLine(),
    highlightActiveLineGutter(),
    history(),
    foldGutter(),
    indentOnInput(),
    bracketMatching(),
    highlightSelectionMatches(),
    keymap.of([
      ...defaultKeymap,
      ...historyKeymap,
      ...searchKeymap,
      indentWithTab,
      { key: 'Mod-s', run: () => { saveCurrentFile(); return true } },
    ]),
    getLanguageExtension(syntax),
    EditorView.updateListener.of((update) => {
      if (update.docChanged && activeTab.value) {
        activeTab.value.modified = true
        activeTab.value.content = update.state.doc.toString()
      }
      if (update.selectionSet) {
        const pos = update.state.selection.main.head
        const line = update.state.doc.lineAt(pos)
        cursorPosition.value = { line: line.number, col: pos - line.from + 1 }
      }
    }),
  ]

  if (props.darkMode) {
    extensions.push(oneDark)
  } else {
    extensions.push(syntaxHighlighting(defaultHighlightStyle, { fallback: true }))
  }

  return EditorState.create({ doc: content, extensions })
}

function mountEditor(content, syntax) {
  if (editorView.value) {
    editorView.value.destroy()
  }

  if (!editorContainer.value) return

  const state = createEditorState(content, syntax)
  editorView.value = new EditorView({
    state,
    parent: editorContainer.value,
  })
}

function openFile(path, name, content, syntax, size = 0) {
  const existing = tabs.value.find(t => t.path === path)
  if (existing) {
    switchTab(path)
    return
  }

  tabs.value.push({
    path,
    name,
    content,
    originalContent: content,
    syntax,
    size,
    modified: false,
  })

  activeTabPath.value = path
  nextTick(() => mountEditor(content, syntax))
}

function switchTab(path) {
  // Save current content
  if (editorView.value && activeTab.value) {
    activeTab.value.content = editorView.value.state.doc.toString()
  }

  activeTabPath.value = path
  const tab = tabs.value.find(t => t.path === path)
  if (tab) {
    nextTick(() => mountEditor(tab.content, tab.syntax))
  }
}

function closeTab(path) {
  const tab = tabs.value.find(t => t.path === path)
  if (tab && tab.modified) {
    if (!confirm(t('fileManager.unsavedChanges'))) return
  }

  const idx = tabs.value.findIndex(t => t.path === path)
  tabs.value.splice(idx, 1)

  if (activeTabPath.value === path) {
    if (tabs.value.length > 0) {
      const newIdx = Math.min(idx, tabs.value.length - 1)
      switchTab(tabs.value[newIdx].path)
    } else {
      activeTabPath.value = null
      if (editorView.value) {
        editorView.value.destroy()
        editorView.value = null
      }
      emit('close')
    }
  }
}

async function saveCurrentFile() {
  if (!activeTab.value || !activeTab.value.modified) return

  if (editorView.value) {
    activeTab.value.content = editorView.value.state.doc.toString()
  }

  emit('save', {
    path: activeTab.value.path,
    content: activeTab.value.content,
    callback: (success) => {
      if (success && activeTab.value) {
        activeTab.value.modified = false
        activeTab.value.originalContent = activeTab.value.content
      }
    },
  })
}

function formatSize(bytes) {
  if (!bytes) return ''
  const units = ['B', 'KB', 'MB']
  let i = 0
  let size = bytes
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
  return `${size.toFixed(i > 0 ? 1 : 0)} ${units[i]}`
}

// Re-mount editor when dark mode changes
watch(() => props.darkMode, () => {
  if (activeTab.value && editorView.value) {
    activeTab.value.content = editorView.value.state.doc.toString()
    nextTick(() => mountEditor(activeTab.value.content, activeTab.value.syntax))
  }
})

onBeforeUnmount(() => {
  if (editorView.value) {
    editorView.value.destroy()
  }
})

defineExpose({ openFile, closeTab, tabs, activeTabPath })
</script>
