<template>
  <div v-if="show" class="modal-overlay" ref="modalContainer" @click.self="handleOverlayClick">
    <div
        class="modal-container"
        :style="modalStyles"
        :class="['modal-size-' + size, { 'modal-resizable': resizable }]"
    >
      <!-- Pasek tytułu (jeśli przekazano title) -->
      <div v-if="title" class="modal-header">
        <h3>{{ title }}</h3>
        <button
            v-if="showCloseButton"
            class="close-modal-button"
            @click="$emit('close')"
            aria-label="Close modal"
        >
          <i class="fa-solid fa-times"></i>
        </button>
      </div>

      <!-- Treść modala -->
      <div class="modal-body" :class="{ 'no-header': !title }">
        <slot></slot>
      </div>

      <!-- Pasek dolny z przyciskami (jeśli przekazano footerButtons) -->
      <div v-if="footerButtons && footerButtons.length > 0" class="modal-footer">
        <button
            v-for="(button, index) in footerButtons"
            :key="index"
            :class="button.class || 'btn-primary'"
            @click="button.onClick"
        >
          {{ button.label }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'medium',
    validator: (value) => ['small', 'medium', 'large', 'custom'].includes(value),
  },
  customWidth: {
    type: String,
    default: 'auto',
  },
  customHeight: {
    type: String,
    default: 'auto',
  },
  showCloseButton: {
    type: Boolean,
    default: true,
  },
  footerButtons: {
    type: Array,
    default: () => [],
    validator: (buttons) => {
      return buttons.every(
          (button) =>
              typeof button === 'object' &&
              button.label &&
              typeof button.onClick === 'function'
      );
    },
  },
  resizable: {
    type: Boolean,
    default: false,
  },
  closeOnOverlayClick: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(['close']);

// Obliczanie stylów modala na podstawie size, customWidth i customHeight
const modalStyles = computed(() => {
  let width, height, minHeight;

  switch (props.size) {
    case 'small':
      width = '400px';
      height = 'auto';
      minHeight = '200px';
      break;
    case 'medium':
      width = '600px';
      height = 'auto';
      minHeight = '300px';
      break;
    case 'large':
      width = '800px';
      height = 'auto';
      minHeight = '400px';
      break;
    case 'custom':
      width = props.customWidth || 'auto';
      height = props.customHeight || 'auto';
      minHeight = 'auto';
      break;
    default:
      width = '600px';
      height = 'auto';
      minHeight = '300px';
  }

  return {
    width,
    height: props.resizable ? 'auto' : height,
    minHeight,
    maxWidth: '98vw',
    maxHeight: '90vh',
  };
});

// Referencja do kontenera modala
const modalContainer = ref(null);

// Funkcja sprawdzająca, czy modal jest na wierzchu
const isTopModal = () => {
  const modals = document.querySelectorAll('.modal-overlay');
  let maxZIndex = -1;
  let topModal = null;

  modals.forEach((modal) => {
    const zIndex = parseInt(window.getComputedStyle(modal).zIndex, 10) || 0;
    if (zIndex >= maxZIndex) {
      maxZIndex = zIndex;
      topModal = modal;
    }
  });

  const isTop = topModal === modalContainer.value;
  console.log('isTopModal:', isTop, 'zIndex:', maxZIndex, 'modalContainer:', modalContainer.value);
  return isTop;
};

// Obsługa klawisza Esc
const handleEscKey = (event) => {
  console.log('Esc naciśnięty, show:', props.show, 'isTopModal:', isTopModal());
  if (event.key === 'Escape' && props.show && isTopModal()) {
    emit('close');
  }
};

// Obsługa kliknięcia w overlay
const handleOverlayClick = () => {
  if (props.closeOnOverlayClick) {
    emit('close');
  }
};

// Dodanie nasłuchiwania klawisza Esc
onMounted(() => {
  document.addEventListener('keydown', handleEscKey);
});

// Usunięcie nasłuchiwania po zniszczeniu komponentu
onUnmounted(() => {
  document.removeEventListener('keydown', handleEscKey);
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-container {
  background: var(--background-light);
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  position: relative;
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  max-height: 90vh;
  overflow: hidden;
}

body.dark-theme .modal-container {
  background: var(--dark-background-secondary);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.modal-resizable {
  height: auto !important;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

body.dark-theme .modal-header {
  border-bottom: 1px solid var(--dark-border);
}

.modal-header h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text-dark);
  margin: 0;
}

body.dark-theme .modal-header h3 {
  color: var(--dark-text);
}

.close-modal-button {
  background: none;
  border: none;
  font-size: 24px;
  color: var(--text-color);
  cursor: pointer;
  transition: color 0.3s ease, transform 0.2s ease;
}

body.dark-theme .close-modal-button {
  color: var(--dark-text);
}

.close-modal-button:hover {
  color: var(--error-color);
  transform: rotate(90deg);
}

body.dark-theme .close-modal-button:hover {
  color: var(--dark-error);
}

.modal-body {
  padding: 20px;
  flex: 1 1 auto;
  overflow-y: auto;
  box-sizing: border-box;
}

.modal-body.no-header {
  padding-top: 20px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 15px 20px;
  border-top: 1px solid var(--border-color);
  background: var(--background-light);
  flex-shrink: 0;
  z-index: 10;
  gap: 10px;
}

body.dark-theme .modal-footer {
  border-top: 1px solid var(--dark-border);
  background: var(--dark-background-secondary);
}

.modal-footer button {
  padding: 10px 20px;
  border-radius: 6px;
  font-size: var(--font-size-base);
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
}

.btn-primary {
  background-color: var(--primary-color);
  color: white;
  border: none;
}

body.dark-theme .btn-primary {
  background-color: var(--dark-primary);
}

.btn-primary:hover {
  background-color: var(--primary-hover);
  transform: translateY(-2px);
}

body.dark-theme .btn-primary:hover {
  background-color: var(--dark-primary-hover);
}

.btn-white {
  background-color: white;
  color: var(--text-dark);
  border: 1px solid var(--border-color);
}

body.dark-theme .btn-white {
  background-color: var(--dark-background-secondary);
  color: var(--dark-text);
  border: 1px solid var(--dark-border);
}

.btn-white:hover {
  background-color: var(--background-light-secondary);
  transform: translateY(-2px);
}

body.dark-theme .btn-white:hover {
  background-color: #555;
}

.btn-error {
  background-color: var(--error-color);
  color: white;
  border: none;
}

body.dark-theme .btn-error {
  background-color: var(--dark-error);
}

.btn-error:hover {
  background-color: var(--error-hover);
  transform: translateY(-2px);
}

body.dark-theme .btn-error:hover {
  background-color: var(--dark-error-hover);
}

@media (max-width: 600px) {
  .modal-overlay {
    display: block;
    overflow: hidden;
    min-height: 100vh;
  }

  .modal-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 98vw !important;
    max-width: 98vw !important;
    margin: 0;
    box-sizing: border-box;
  }

  .modal-size-large {
    height: 98vh !important;
    max-height: 98vh !important;
    min-height: 98vh !important;
  }

  .modal-size-small,
  .modal-size-medium,
  .modal-size-custom {
    height: auto !important;
    max-height: 90vh !important;
    min-height: 50vh;
  }

  .modal-header {
    padding: 10px 15px;
  }

  .modal-body {
    padding: 15px;
  }

  .modal-footer {
    padding: 10px 15px;
  }

  .modal-footer button {
    padding: 8px 16px;
    font-size: 0.9rem;
  }

  .close-modal-button {
    font-size: 20px;
  }
}

@media (max-width: 400px) {
  .modal-header {
    padding: 8px 10px;
  }

  .modal-body {
    padding: 10px;
  }

  .modal-footer {
    padding: 8px 10px;
    flex-wrap: wrap;
    gap: 8px;
  }

  .modal-footer button {
    padding: 6px 12px;
    font-size: 0.85rem;
    flex: 1;
    text-align: center;
  }
}
</style>
