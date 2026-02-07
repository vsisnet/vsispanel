<template>
  <div class="h-[calc(100vh-7rem)] flex flex-col">
    <!-- Toolbar -->
    <div class="flex items-center justify-between bg-gray-900 border-b border-gray-700 px-3 py-1.5 rounded-t-lg">
      <!-- Tabs -->
      <div class="flex items-center gap-1 overflow-x-auto flex-1 min-w-0">
        <div v-for="tab in tabs" :key="tab.id"
          :class="['flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-t cursor-pointer select-none transition-colors max-w-[180px]',
            activeTabId === tab.id
              ? 'bg-gray-800 text-white'
              : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800/50']"
          @click="switchTab(tab.id)"
          @dblclick="startRenaming(tab)">
          <CommandLineIcon class="w-3.5 h-3.5 flex-shrink-0" />
          <span v-if="renamingTabId !== tab.id" class="truncate">{{ tab.name }}</span>
          <input v-else
            ref="renameInputRef"
            v-model="renameValue"
            @keydown.enter="finishRenaming(tab)"
            @keydown.escape="cancelRenaming"
            @blur="finishRenaming(tab)"
            class="bg-transparent border-b border-gray-500 text-white text-xs w-20 outline-none" />
          <span v-if="!tab.connected" class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0" :title="$t('terminal.disconnected')"></span>
          <button v-if="tabs.length > 1" @click.stop="closeTab(tab.id)" class="text-gray-500 hover:text-red-400 flex-shrink-0">
            <XMarkIcon class="w-3.5 h-3.5" />
          </button>
        </div>
        <button @click="addTab" class="p-1.5 text-gray-400 hover:text-white hover:bg-gray-800 rounded" :title="$t('terminal.newTab')">
          <PlusIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-1 ml-2 flex-shrink-0">
        <button @click="reconnectTab" class="p-1.5 text-gray-400 hover:text-white rounded" :title="$t('terminal.reconnect')">
          <ArrowPathIcon class="w-4 h-4" />
        </button>
        <button @click="showSettings = !showSettings" class="p-1.5 text-gray-400 hover:text-white rounded" :title="$t('terminal.settings')">
          <Cog6ToothIcon class="w-4 h-4" />
        </button>
        <button @click="toggleFullscreen" class="p-1.5 text-gray-400 hover:text-white rounded" :title="$t('terminal.fullscreen')">
          <ArrowsPointingOutIcon v-if="!isFullscreen" class="w-4 h-4" />
          <ArrowsPointingInIcon v-else class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Main Area -->
    <div class="flex flex-1 min-h-0 relative">
      <!-- Terminal Container -->
      <div ref="terminalContainer" class="flex-1 bg-gray-900 relative rounded-b-lg overflow-hidden"
        :class="{ 'rounded-br-none': showSettings }">
        <div v-for="tab in tabs" :key="tab.id"
          :ref="el => setTabRef(tab.id, el)"
          :class="['absolute inset-0', activeTabId === tab.id ? 'block' : 'hidden']">
        </div>
        <!-- Connection overlay -->
        <div v-if="activeTab && !activeTab.connected && !activeTab.connecting"
          class="absolute inset-0 flex items-center justify-center bg-gray-900/80 z-10">
          <div class="text-center">
            <XCircleIcon class="w-12 h-12 text-red-400 mx-auto mb-3" />
            <p class="text-gray-300 text-sm mb-3">{{ $t('terminal.connectionLost') }}</p>
            <button @click="reconnectTab" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700">
              {{ $t('terminal.reconnect') }}
            </button>
          </div>
        </div>
        <!-- Connecting overlay -->
        <div v-if="activeTab && activeTab.connecting"
          class="absolute inset-0 flex items-center justify-center bg-gray-900/80 z-10">
          <div class="text-center">
            <ArrowPathIcon class="w-10 h-10 text-blue-400 mx-auto mb-3 animate-spin" />
            <p class="text-gray-300 text-sm">{{ $t('terminal.connecting') }}</p>
          </div>
        </div>
      </div>

      <!-- Settings Panel -->
      <transition name="slide">
        <div v-if="showSettings" class="w-72 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 overflow-y-auto rounded-br-lg">
          <div class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('terminal.settings') }}</h3>
              <button @click="showSettings = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Font Size -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                {{ $t('terminal.fontSize') }}: {{ settings.fontSize }}px
              </label>
              <input type="range" v-model.number="settings.fontSize" min="10" max="20" step="1"
                class="w-full accent-primary-500" @change="applySettings" />
            </div>

            <!-- Font Family -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ $t('terminal.fontFamily') }}</label>
              <select v-model="settings.fontFamily" @change="applySettings"
                class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="'JetBrains Mono', 'Fira Code', monospace">JetBrains Mono</option>
                <option value="'Fira Code', monospace">Fira Code</option>
                <option value="'Source Code Pro', monospace">Source Code Pro</option>
                <option value="'Cascadia Code', monospace">Cascadia Code</option>
                <option value="monospace">System Monospace</option>
              </select>
            </div>

            <!-- Color Scheme -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ $t('terminal.colorScheme') }}</label>
              <div class="grid grid-cols-2 gap-1.5">
                <button v-for="scheme in colorSchemes" :key="scheme.name"
                  @click="settings.colorScheme = scheme.name; applySettings()"
                  :class="['px-2 py-1.5 text-xs rounded border transition-colors',
                    settings.colorScheme === scheme.name
                      ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                      : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-400']">
                  {{ scheme.name }}
                </button>
              </div>
            </div>

            <!-- Cursor Style -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ $t('terminal.cursorStyle') }}</label>
              <select v-model="settings.cursorStyle" @change="applySettings"
                class="w-full text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1.5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="block">Block</option>
                <option value="underline">Underline</option>
                <option value="bar">Bar</option>
              </select>
            </div>

            <!-- Scrollback -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                {{ $t('terminal.scrollback') }}: {{ settings.scrollback }}
              </label>
              <input type="range" v-model.number="settings.scrollback" min="500" max="10000" step="500"
                class="w-full accent-primary-500" @change="applySettings" />
            </div>

            <!-- Keyboard Shortcuts -->
            <div class="mb-4">
              <h4 class="text-xs font-semibold text-gray-900 dark:text-white mb-2">{{ $t('terminal.keyboardShortcuts') }}</h4>
              <div class="space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                <div class="flex justify-between">
                  <span>{{ $t('terminal.copy') }}</span>
                  <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px] font-mono">Ctrl+C</kbd>
                </div>
                <div class="flex justify-between">
                  <span>{{ $t('terminal.paste') }}</span>
                  <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px] font-mono">Ctrl+V</kbd>
                </div>
                <div class="flex justify-between">
                  <span>{{ $t('terminal.copyAlt') }}</span>
                  <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px] font-mono">Ctrl+Shift+C</kbd>
                </div>
                <div class="flex justify-between">
                  <span>{{ $t('terminal.pasteAlt') }}</span>
                  <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px] font-mono">Ctrl+Shift+V</kbd>
                </div>
                <p class="text-[10px] text-gray-500 dark:text-gray-500 mt-1 italic">{{ $t('terminal.copyHint') }}</p>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { Terminal } from 'xterm'
import { FitAddon } from '@xterm/addon-fit'
import { SearchAddon } from '@xterm/addon-search'
import { WebLinksAddon } from '@xterm/addon-web-links'
import 'xterm/css/xterm.css'
import {
  CommandLineIcon,
  PlusIcon,
  XMarkIcon,
  ArrowPathIcon,
  Cog6ToothIcon,
  ArrowsPointingOutIcon,
  ArrowsPointingInIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()

const terminalContainer = ref(null)
const showSettings = ref(false)
const isFullscreen = ref(false)
const renamingTabId = ref(null)
const renameValue = ref('')
const renameInputRef = ref(null)

// Tab refs map
const tabRefs = {}
function setTabRef(tabId, el) {
  if (el) tabRefs[tabId] = el
}

// Terminal settings
const settings = reactive({
  fontSize: 14,
  fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
  colorScheme: 'Dark',
  cursorStyle: 'block',
  scrollback: 5000,
})

// Load settings from localStorage
const savedSettings = localStorage.getItem('terminal-settings')
if (savedSettings) {
  try {
    Object.assign(settings, JSON.parse(savedSettings))
  } catch (e) { /* ignore */ }
}

const colorSchemes = [
  {
    name: 'Dark',
    theme: {
      background: '#1a1a2e',
      foreground: '#e0e0e0',
      cursor: '#e0e0e0',
      cursorAccent: '#1a1a2e',
      selectionBackground: '#3a3a5e',
      black: '#1a1a2e',
      red: '#e74c3c',
      green: '#2ecc71',
      yellow: '#f1c40f',
      blue: '#3498db',
      magenta: '#9b59b6',
      cyan: '#1abc9c',
      white: '#ecf0f1',
      brightBlack: '#34495e',
      brightRed: '#e74c3c',
      brightGreen: '#2ecc71',
      brightYellow: '#f1c40f',
      brightBlue: '#3498db',
      brightMagenta: '#9b59b6',
      brightCyan: '#1abc9c',
      brightWhite: '#ffffff',
    }
  },
  {
    name: 'Monokai',
    theme: {
      background: '#272822',
      foreground: '#f8f8f2',
      cursor: '#f8f8f0',
      cursorAccent: '#272822',
      selectionBackground: '#49483e',
      black: '#272822',
      red: '#f92672',
      green: '#a6e22e',
      yellow: '#f4bf75',
      blue: '#66d9ef',
      magenta: '#ae81ff',
      cyan: '#a1efe4',
      white: '#f8f8f2',
      brightBlack: '#75715e',
      brightRed: '#f92672',
      brightGreen: '#a6e22e',
      brightYellow: '#f4bf75',
      brightBlue: '#66d9ef',
      brightMagenta: '#ae81ff',
      brightCyan: '#a1efe4',
      brightWhite: '#f9f8f5',
    }
  },
  {
    name: 'Solarized',
    theme: {
      background: '#002b36',
      foreground: '#839496',
      cursor: '#839496',
      cursorAccent: '#002b36',
      selectionBackground: '#073642',
      black: '#073642',
      red: '#dc322f',
      green: '#859900',
      yellow: '#b58900',
      blue: '#268bd2',
      magenta: '#d33682',
      cyan: '#2aa198',
      white: '#eee8d5',
      brightBlack: '#586e75',
      brightRed: '#cb4b16',
      brightGreen: '#586e75',
      brightYellow: '#657b83',
      brightBlue: '#839496',
      brightMagenta: '#6c71c4',
      brightCyan: '#93a1a1',
      brightWhite: '#fdf6e3',
    }
  },
  {
    name: 'Light',
    theme: {
      background: '#ffffff',
      foreground: '#333333',
      cursor: '#333333',
      cursorAccent: '#ffffff',
      selectionBackground: '#add6ff',
      black: '#000000',
      red: '#cd3131',
      green: '#00bc00',
      yellow: '#949800',
      blue: '#0451a5',
      magenta: '#bc05bc',
      cyan: '#0598bc',
      white: '#555555',
      brightBlack: '#666666',
      brightRed: '#cd3131',
      brightGreen: '#14ce14',
      brightYellow: '#b5ba00',
      brightBlue: '#0451a5',
      brightMagenta: '#bc05bc',
      brightCyan: '#0598bc',
      brightWhite: '#a5a5a5',
    }
  },
]

// Tab management
let tabCounter = 0
const tabs = ref([])
const activeTabId = ref(null)

const activeTab = computed(() => tabs.value.find(t => t.id === activeTabId.value))

function getSchemeTheme(name) {
  return colorSchemes.find(s => s.name === name)?.theme || colorSchemes[0].theme
}

function createTerminalInstance() {
  const theme = getSchemeTheme(settings.colorScheme)
  const term = new Terminal({
    fontSize: settings.fontSize,
    fontFamily: settings.fontFamily,
    cursorStyle: settings.cursorStyle,
    scrollback: settings.scrollback,
    theme,
    allowProposedApi: true,
    convertEol: true,
  })

  const fitAddon = new FitAddon()
  const searchAddon = new SearchAddon()
  const webLinksAddon = new WebLinksAddon()

  term.loadAddon(fitAddon)
  term.loadAddon(searchAddon)
  term.loadAddon(webLinksAddon)

  return { term, fitAddon, searchAddon }
}

async function addTab() {
  const id = `tab_${++tabCounter}`
  const { term, fitAddon, searchAddon } = createTerminalInstance()

  const tab = reactive({
    id,
    name: `Terminal ${tabCounter}`,
    term,
    fitAddon,
    searchAddon,
    ws: null,
    connected: false,
    connecting: true,
  })

  tabs.value.push(tab)
  activeTabId.value = id

  await nextTick()

  const el = tabRefs[id]
  if (el) {
    term.open(el)
    fitAddon.fit()

    // Handle input
    term.onData((data) => {
      if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
        tab.ws.send(JSON.stringify({ type: 'input', data }))
      }
    })

    // Handle keyboard shortcuts (copy/paste/search)
    term.attachCustomKeyEventHandler((e) => {
      if (e.type !== 'keydown') return true

      // Ctrl+Shift+F: Search
      if (e.ctrlKey && e.shiftKey && e.key === 'F') {
        return false
      }

      // Ctrl+Shift+C: Always copy selection
      if (e.ctrlKey && e.shiftKey && e.key === 'C') {
        const selection = term.getSelection()
        if (selection) {
          navigator.clipboard.writeText(selection)
        }
        return false
      }

      // Ctrl+Shift+V: Paste from clipboard
      if (e.ctrlKey && e.shiftKey && e.key === 'V') {
        navigator.clipboard.readText().then(text => {
          if (text && tab.ws && tab.ws.readyState === WebSocket.OPEN) {
            tab.ws.send(JSON.stringify({ type: 'input', data: text }))
          }
        })
        return false
      }

      // Ctrl+C: Copy if text selected, otherwise send SIGINT
      if (e.ctrlKey && !e.shiftKey && e.key === 'c') {
        if (term.hasSelection()) {
          navigator.clipboard.writeText(term.getSelection())
          term.clearSelection()
          return false
        }
        return true
      }

      // Ctrl+V: Paste from clipboard
      if (e.ctrlKey && !e.shiftKey && e.key === 'v') {
        navigator.clipboard.readText().then(text => {
          if (text && tab.ws && tab.ws.readyState === WebSocket.OPEN) {
            tab.ws.send(JSON.stringify({ type: 'input', data: text }))
          }
        })
        return false
      }

      return true
    })

    connectTab(tab)
  }
}

async function connectTab(tab) {
  tab.connecting = true
  tab.connected = false

  try {
    const { data } = await api.post('/terminal/sessions')
    if (!data.success) {
      tab.term.writeln(`\r\n\x1b[31m${t('terminal.connectionFailed')}\x1b[0m`)
      tab.connecting = false
      return
    }

    const { token, ws_url } = data.data
    const ws = new WebSocket(ws_url)

    ws.onopen = () => {
      ws.send(JSON.stringify({
        type: 'auth',
        token,
        cols: tab.term.cols,
        rows: tab.term.rows,
      }))
    }

    ws.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data)
        switch (msg.type) {
          case 'auth':
            if (msg.success) {
              tab.connected = true
              tab.connecting = false
              tab.sessionId = msg.sessionId

              // Handle resize
              tab.term.onResize(({ cols, rows }) => {
                if (ws.readyState === WebSocket.OPEN) {
                  ws.send(JSON.stringify({ type: 'resize', cols, rows }))
                }
              })
            } else {
              tab.term.writeln(`\r\n\x1b[31mAuth failed: ${msg.message}\x1b[0m`)
              tab.connecting = false
            }
            break
          case 'output':
            tab.term.write(msg.data)
            break
          case 'exit':
            tab.term.writeln(`\r\n\x1b[33m${t('terminal.sessionEnded')} (code: ${msg.code})\x1b[0m`)
            tab.connected = false
            break
          case 'error':
            tab.term.writeln(`\r\n\x1b[31m${msg.message}\x1b[0m`)
            break
        }
      } catch (e) {
        // Ignore parse errors
      }
    }

    ws.onclose = () => {
      tab.connected = false
      tab.connecting = false
    }

    ws.onerror = () => {
      tab.connected = false
      tab.connecting = false
      tab.term.writeln(`\r\n\x1b[31m${t('terminal.connectionFailed')}\x1b[0m`)
    }

    tab.ws = ws

    // Keep alive ping
    tab.pingInterval = setInterval(() => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ type: 'ping' }))
      }
    }, 30000)
  } catch (e) {
    tab.term.writeln(`\r\n\x1b[31m${t('terminal.connectionFailed')}\x1b[0m`)
    tab.connecting = false
  }
}

function switchTab(id) {
  activeTabId.value = id
  nextTick(() => {
    const tab = tabs.value.find(t => t.id === id)
    if (tab) {
      tab.fitAddon.fit()
      tab.term.focus()
    }
  })
}

function closeTab(id) {
  const tab = tabs.value.find(t => t.id === id)
  if (tab) {
    if (tab.ws) tab.ws.close()
    if (tab.pingInterval) clearInterval(tab.pingInterval)
    tab.term.dispose()
    delete tabRefs[id]
  }

  tabs.value = tabs.value.filter(t => t.id !== id)

  if (activeTabId.value === id && tabs.value.length > 0) {
    switchTab(tabs.value[tabs.value.length - 1].id)
  }
}

function reconnectTab() {
  const tab = activeTab.value
  if (!tab) return
  if (tab.ws) tab.ws.close()
  if (tab.pingInterval) clearInterval(tab.pingInterval)
  tab.term.clear()
  connectTab(tab)
}

function startRenaming(tab) {
  renamingTabId.value = tab.id
  renameValue.value = tab.name
  nextTick(() => {
    const input = renameInputRef.value
    if (input && input[0]) input[0].focus()
  })
}

function finishRenaming(tab) {
  if (renameValue.value.trim()) {
    tab.name = renameValue.value.trim()
  }
  renamingTabId.value = null
}

function cancelRenaming() {
  renamingTabId.value = null
}

function applySettings() {
  localStorage.setItem('terminal-settings', JSON.stringify({ ...settings }))
  const theme = getSchemeTheme(settings.colorScheme)

  tabs.value.forEach(tab => {
    tab.term.options.fontSize = settings.fontSize
    tab.term.options.fontFamily = settings.fontFamily
    tab.term.options.cursorStyle = settings.cursorStyle
    tab.term.options.scrollback = settings.scrollback
    tab.term.options.theme = theme
    tab.fitAddon.fit()
  })
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    terminalContainer.value?.parentElement?.requestFullscreen()
    isFullscreen.value = true
  } else {
    document.exitFullscreen()
    isFullscreen.value = false
  }
}

// Handle resize
let resizeObserver = null

onMounted(() => {
  addTab()

  resizeObserver = new ResizeObserver(() => {
    const tab = activeTab.value
    if (tab) {
      try {
        tab.fitAddon.fit()
      } catch (e) { /* ignore */ }
    }
  })

  if (terminalContainer.value) {
    resizeObserver.observe(terminalContainer.value)
  }

  document.addEventListener('fullscreenchange', () => {
    isFullscreen.value = !!document.fullscreenElement
    nextTick(() => {
      const tab = activeTab.value
      if (tab) tab.fitAddon.fit()
    })
  })
})

onBeforeUnmount(() => {
  tabs.value.forEach(tab => {
    if (tab.ws) tab.ws.close()
    if (tab.pingInterval) clearInterval(tab.pingInterval)
    tab.term.dispose()
  })
  if (resizeObserver) resizeObserver.disconnect()
})

// Refit on settings panel toggle
watch(showSettings, () => {
  nextTick(() => {
    const tab = activeTab.value
    if (tab) tab.fitAddon.fit()
  })
})
</script>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: width 0.2s ease, opacity 0.2s ease;
}
.slide-enter-from, .slide-leave-to {
  width: 0;
  opacity: 0;
}
</style>
