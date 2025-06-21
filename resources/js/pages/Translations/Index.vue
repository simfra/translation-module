<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import type { BreadcrumbItem } from '@/types';
import { getCurrentInstance } from 'vue';
import UniversalModal from '../../Components/UniversalModal.vue';
import axios from 'axios';
import { push } from 'notivue';
import { debounce } from 'lodash';

const debouncedSearch = debounce((callback) => callback(), 300);

const proxy = getCurrentInstance()?.proxy;

const langs = ref<{ code: string; name: string }[]>([]);
const selectedLang = ref('pl');
const translations = ref<Record<string, { id: number | null; key: string; value: string; readonly: boolean }>>({});
const allKeys = ref<string[]>([]);
const groups = ref<string[]>(['all']);
const pagination = ref<{
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  steps: Array<{
    label: string;
    value: number | null;
    type: 'page' | 'prev' | 'next' | 'ellipsis';
    disabled: boolean;
    active?: boolean;
  }>;
}>({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  steps: [],
});
const searchQuery = ref('');
const selectedGroup = ref('all');
const showMissing = ref(false);
const localTranslations = ref<Record<string, string>>({});
const originalTranslations = ref<Record<string, string>>({});
const fileInput = ref<HTMLInputElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const keyError = ref<string | null>(null);
const isSaving = ref<boolean>(false);
const isLoading = ref<boolean>(false);
const errorMessage = ref<string | null>(null);
const goToPageInput = ref('');

const showModal = ref(false);
const modalKey = ref('');
const modalValue = ref('');

const fetchTranslations = async () => {
  if (isLoading.value) return;
  isLoading.value = true;
  errorMessage.value = null;
  try {
    const response = await axios.get(route('translations.get'), {
      params: {
        lang: selectedLang.value,
        page: pagination.value.current_page,
        search: searchQuery.value || null,
        per_page: pagination.value.per_page,
        group: selectedGroup.value === 'all' ? null : selectedGroup.value,
        missing: showMissing.value ? 1 : null,
      },
    });
    langs.value = response.data.langs || [];
    if (response.data.selected_lang && response.data.selected_lang !== selectedLang.value) {
      selectedLang.value = response.data.selected_lang;
      localStorage.setItem('selectedLang', selectedLang.value);
    }
    translations.value = response.data.translations || {};
    allKeys.value = (response.data.all_keys || []).filter((key: string) => key && typeof key === 'string');
    groups.value = response.data.groups?.length ? ['all', ...response.data.groups.sort()] : ['all'];
    pagination.value = response.data.pagination || {
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0,
      steps: [],
    };
    if (response.data.search !== searchQuery.value) {
      searchQuery.value = response.data.search || '';
    }
    goToPageInput.value = String(pagination.value.current_page);
    Object.assign(localTranslations.value, Object.fromEntries(
        allKeys.value.map((key) => [key, translations.value[key]?.value || ''])
    ));
    Object.assign(originalTranslations.value, localTranslations.value);
    if (allKeys.value.length === 0) {
      errorMessage.value = proxy?.$t('no_translations_found') || 'No translations found';
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || proxy?.$t('error_loading_data') || 'Error loading data';
    push.error({ message: errorMessage.value });
    langs.value = [];
    translations.value = {};
    allKeys.value = [];
    groups.value = ['all'];
  } finally {
    isLoading.value = false;
    if (searchInput.value && searchQuery.value) {
      searchInput.value.focus();
    }
  }
};

const hasChanges = computed(() => {
  return Object.entries(localTranslations.value).some(
      ([key, value]) => value !== originalTranslations.value[key]
  );
});

const breadcrumbs: BreadcrumbItem[] = [
  { title: proxy?.$t('nav.dashboard') || 'Dashboard', href: route('dashboard') },
  { title: proxy?.$t('translations') || 'Translations', href: route('translations.index') },
];

const changeLanguage = () => {
  pagination.value.current_page = 1;
  selectedGroup.value = 'all';
  showMissing.value = false;
  localStorage.setItem('selectedLang', selectedLang.value);
  fetchTranslations();
};

const changePage = (page: number | null) => {
  if (!page || allKeys.value.length === 0) return;
  pagination.value.current_page = page;
  goToPageInput.value = String(page);
  fetchTranslations();
};

const goToPage = () => {
  const inputValue = parseInt(goToPageInput.value, 10);
  if (isNaN(inputValue) || inputValue < 1 || inputValue > pagination.value.last_page) {
    push.error({ message: proxy?.$t('error.invalid_page_number') || 'Invalid page number' });
    goToPageInput.value = String(pagination.value.current_page);
    return;
  }
  changePage(inputValue);
};

const openAddKeyModal = () => {
  modalKey.value = '';
  modalValue.value = '';
  showModal.value = true;
};

const addKey = async () => {
  const key = modalKey.value.trim();
  if (!key) {
    keyError.value = proxy?.$t('error.key_required') || 'Key is required';
    push.error({ message: keyError.value });
    return;
  }
  if (allKeys.value.includes(key)) {
    keyError.value = proxy?.$t('error.key_exists') || 'Key already exists';
    push.error({ message: keyError.value });
    return;
  }

  try {
    isSaving.value = true;
    const response = await axios.post(route('translations.store'), {
      lang: selectedLang.value,
      key,
      value: modalValue.value,
    });
    if (response.data.success) {
      Object.assign(localTranslations.value, { [key]: modalValue.value });
      Object.assign(originalTranslations.value, { [key]: modalValue.value });
      translations.value = {
        ...translations.value,
        [key]: {
          id: response.data.translation.id,
          key: response.data.translation.key,
          value: response.data.translation.value,
          readonly: response.data.translation.readonly,
        },
      };
      allKeys.value = [...allKeys.value, key];
      showModal.value = false;
      keyError.value = null;
      errorMessage.value = null;
      push.success({ message: proxy?.$t(response.data.message || 'success.created') || 'Translation created' });
    } else {
      keyError.value = proxy?.$t(response.data.message || 'error.error_adding_key') || 'Error adding key';
      push.error({ message: keyError.value });
    }
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    keyError.value = errors.key ? errors.key[0] : proxy?.$t(error.response?.data?.message || 'error.error_adding_key') || 'Error adding key';
    push.error({ message: keyError.value });
  } finally {
    isSaving.value = false;
  }
};

const saveTranslation = async (key: string) => {
  if (!key || typeof key !== 'string' || !localTranslations.value[key]) {
    push.error({ message: proxy?.$t('error.invalid_key') || 'Invalid translation key' });
    return;
  }
  try {
    isSaving.value = true;
    const response = await axios.post(route('translations.store'), {
      lang: selectedLang.value,
      key,
      value: localTranslations.value[key],
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'error.error_saving_translation');
    }
    console.log('Saving translation:', response.data.translation);
    translations.value = {
      ...translations.value,
      [key]: {
        id: response.data.translation.id,
        key: response.data.translation.key,
        value: response.data.translation.value,
        readonly: response.data.translation.readonly,
      },
    };
    Object.assign(originalTranslations.value, { [key]: localTranslations.value[key] });
    push.success({ message: proxy?.$t(response.data.message || 'success.created') || 'Translation saved' });
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    const errorMsg = errors.value ? errors.value[0] : proxy?.$t(error.message || 'error.error_saving_translation') || 'Error saving translation';
    console.error('Save translation error:', error);
    push.error({ message: errorMsg });
  } finally {
    isSaving.value = false;
  }
};

const deleteTranslation = async (key: string, event?: Event) => {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  const translation = translations.value[key];
  if (!translation) {
    push.error({ message: proxy?.$t('error.translation_not_found') || 'Translation not found' });
    return;
  }
  if (translation.readonly) {
    push.error({ message: proxy?.$t('error.cannot_delete_system_translation') || 'Cannot delete system translation' });
    return;
  }
  if (!translation.value.trim()) {
    push.error({ message: proxy?.$t('error.cannot_delete_empty_translation') || 'Cannot delete empty translation' });
    return;
  }
  if (!translation.id) {
    push.error({ message: proxy?.$t('error.translation_not_found') || 'Translation not found' });
    return;
  }

  try {
    isSaving.value = true;
    const response = await axios.delete(route('translations.destroy', { id: translation.id }));
    if (!response.data.success) {
      throw new Error(response.data.message || 'error.error_deleting_translation');
    }
    translations.value = { ...translations.value };
    delete translations.value[key];
    Object.assign(localTranslations.value, { [key]: '' });
    Object.assign(originalTranslations.value, { [key]: '' });
    allKeys.value = allKeys.value.filter(k => k !== key);
    push.success({ message: proxy?.$t(response.data.message || 'success.deleted') || 'Translation deleted' });
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    const errorMsg = errors.id ? errors.id[0] : proxy?.$t(error.message || 'error.error_deleting_translation') || 'Error deleting translation';
    push.error({ message: errorMsg });
  } finally {
    isSaving.value = false;
  }
};

const saveAllTranslations = async () => {
  const translationsToSave = Object.entries(localTranslations.value)
      .filter(([key, value]) => value.trim() !== '' && value !== originalTranslations.value[key])
      .map(([key, value]) => ({ key, value }));

  if (!translationsToSave.length) return;

  try {
    isSaving.value = true;
    const response = await axios.post(route('translations.bulk-store'), {
      lang: selectedLang.value,
      translations: translationsToSave,
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'error.error_saving_translations');
    }
    translations.value = {
      ...translations.value,
      ...Object.fromEntries(
          response.data.translations.map(t => [
            t.key,
            {
              id: t.id,
              key: t.key,
              value: t.value,
              readonly: t.readonly,
            },
          ])
      ),
    };
    translationsToSave.forEach(({ key, value }) => {
      Object.assign(originalTranslations.value, { [key]: value });
    });
    push.success({ message: proxy?.$t(response.data.message || 'success.updated') || 'Translations updated' });
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    const errorMsg = errors.translations ? errors.translations[0] : proxy?.$t(error.message || 'error.error_saving_translations') || 'Error saving translations';
    push.error({ message: errorMsg });
  } finally {
    isSaving.value = false;
  }
};

const importTranslations = async () => {
  if (!fileInput.value?.files?.length) return;

  const formData = new FormData();
  formData.append('lang', selectedLang.value);
  formData.append('file', fileInput.value.files[0]);

  try {
    isSaving.value = true;
    const response = await axios.post(route('translations.import'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'error.error_importing');
    }
    fileInput.value!.value = '';
    push.success({ message: proxy?.$t(response.data.message || 'success.imported') || 'Translations imported' });
    errorMessage.value = null;
    fetchTranslations();
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    const errorMsg = errors.file ? errors.file[0] : proxy?.$t(error.message || 'error.error_importing') || 'Error importing translations';
    push.error({ message: errorMsg });
  } finally {
    isSaving.value = false;
  }
};

onMounted(() => {
  const savedLang = localStorage.getItem('selectedLang');
  if (savedLang) {
    selectedLang.value = savedLang;
  }
  fetchTranslations();
});
</script>

<template>
  <Head title="Tłumaczenia" />
  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <h1 class="text-2xl font-bold mb-4">{{ $t('translations1') }}</h1>
    <div v-if="isLoading && langs.length === 0" class="text-center p-4">
      <svg class="animate-spin h-8 w-8 text-blue-500 mx-auto" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
      <p class="mt-2 text-gray-700">{{ $t('loading') }}</p>
    </div>
    <div v-else-if="langs.length === 0" class="text-center p-4 text-red-500">
      {{ $t('no_languages_available') }}
    </div>
    <div v-else>
      <div class="flex gap-4 mb-4 flex-wrap">
        <select
            v-model="selectedLang"
            @change="changeLanguage"
            class="border p-2 rounded w-48"
            :disabled="isSaving || isLoading"
        >
          <option v-for="lang in langs" :key="lang.code" :value="lang.code">
            {{ lang.name }}
          </option>
        </select>
        <div class="flex items-center gap-2">
          <input
              ref="fileInput"
              type="file"
              accept=".json"
              class="hidden"
              @change="importTranslations"
              :disabled="isSaving || isLoading"
          />
          <button
              class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition-colors"
              @click="fileInput?.click()"
              :disabled="isSaving || isLoading"
          >
            {{ $t('import_file') }}
          </button>
        </div>
        <button
            class="bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600 transition-colors"
            @click="openAddKeyModal"
            :disabled="isSaving || isLoading"
        >
          {{ $t('add_key') }}
        </button>
        <button
            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition-colors"
            @click="fetchTranslations"
            :disabled="isSaving || isLoading"
        >
          {{ $t('refresh') }}
        </button>
        <p v-if="keyError" class="text-red-500 text-sm">{{ keyError }}</p>
      </div>
      <div class="relative min-h-[calc(100vh-200px)] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4 pb-20">
          <div class="mb-4 flex gap-4 flex-wrap">
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('search') }}</label>
              <input
                  ref="searchInput"
                  v-model="searchQuery"
                  type="text"
                  :placeholder="$t('search')"
                  class="mt-1 block w-full max-w-md border p-2 rounded"
                  :disabled="isSaving || isLoading || allKeys.length === 0"
                  @input="debouncedSearch(() => { pagination.current_page = 1; fetchTranslations(); })"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('select_group') }}</label>
              <select
                  v-model="selectedGroup"
                  class="mt-1 block w-full max-w-md border p-2 rounded"
                  :disabled="isSaving || isLoading || allKeys.length === 0"
                  @change="if (allKeys.length) { pagination.current_page = 1; fetchTranslations(); }"
              >
                <option v-for="group in groups" :key="group" :value="group">
                  {{ group === 'all' ? $t('all_groups') : group === 'pozostałe' ? $t('other_groups') : group }}
                </option>
              </select>
            </div>
            <div class="flex items-center">
              <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input
                    v-model="showMissing"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    :disabled="isSaving || isLoading || allKeys.length === 0"
                    @change="if (allKeys.length) { pagination.current_page = 1; fetchTranslations(); }"
                />
                {{ $t('show_missing_translations') }}
              </label>
            </div>
          </div>
          <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-50">
            <svg class="animate-spin h-8 w-8 text-blue-500" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V4a4 4 0 00-4 4H4z" />
            </svg>
          </div>
          <div v-else-if="errorMessage" class="p-4 text-gray-500 text-center">
            {{ errorMessage }}
            <button
                class="mt-2 bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition-colors"
                @click="openAddKeyModal"
                :disabled="isSaving || isLoading"
            >
              {{ $t('add_key') }}
            </button>
          </div>
          <table v-else class="min-w-full border">
            <thead>
            <tr>
              <th class="py-2 px-4 border text-left">{{ $t('key') }}</th>
              <th class="py-2 px-4 border text-left">{{ $t('value') }}</th>
              <th class="py-2 px-4 border text-left">{{ $t('actions') }}</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="key in allKeys" :key="key">
              <td class="py-2 px-4 border align-top">{{ key }}</td>
              <td class="py-2 px-4 border">
                <div class="relative flex-1">
                  <input
                      v-model="localTranslations[key]"
                      type="text"
                      :class="[
                        'w-full p-2 rounded border',
                        !translations[key]?.value ? 'bg-red-100 border-red-300' : 'border-gray-400',
                      ]"
                      :disabled="isSaving || isLoading || translations[key]?.readonly"
                      :title="translations[key]?.readonly ? $t('error.cannot_edit_system_translation') : ''"
                  />
                  <span
                      v-if="!translations[key]?.value"
                      class="absolute right-2 top-1/2 transform -translate-y-1/2 text-red-500"
                      :title="$t('translation_missing')"
                  >
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd"
                        />
                      </svg>
                    </span>
                </div>
              </td>
              <td class="py-2 px-4 border flex items-center gap-2">
                <button
                    class="bg-transparent text-green-500 hover:text-green-700"
                    :title="$t('save')"
                    @click="saveTranslation(key)"
                    :disabled="isSaving || isLoading || translations[key]?.readonly"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"
                    />
                  </svg>
                </button>
                <button
                    class="bg-transparent text-red-500"
                    :class="{
                      'hover:text-red-700': translations[key]?.value && !translations[key]?.readonly,
                      'opacity-50 cursor-not-allowed': !translations[key]?.value || translations[key]?.readonly || isSaving || isLoading,
                    }"
                    :disabled="!translations[key]?.value || translations[key]?.readonly || isSaving || isLoading"
                    :title="translations[key]?.readonly ? $t('error.cannot_delete_system_translation') : !translations[key]?.value ? $t('error.cannot_delete_empty_translation') : $t('delete')"
                    @click="deleteTranslation(key, $event)"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                </button>
              </td>
            </tr>
            </tbody>
          </table>
          <div v-if="allKeys.length > 0" class="mt-4 flex items-center gap-4">
            <div class="flex gap-1 mx-auto">
              <button
                  v-for="step in pagination.steps"
                  :key="step.label"
                  :class="[
                  'px-3 py-1 rounded text-sm font-medium transition-colors',
                  step.active ? 'bg-blue-500 text-white' : step.disabled ? 'bg-gray-200 cursor-not-allowed text-gray-500' : 'bg-gray-200 hover:bg-gray-300 text-gray-700',
                ]"
                  :disabled="step.disabled || isSaving || isLoading"
                  @click="changePage(step.value)"
              >
                {{ step.label }}
              </button>
            </div>
            <div class="flex items-center gap-2 ml-auto">
              <label class="text-sm font-medium text-gray-700">{{ $t('go_to_page') }}</label>
              <input
                  v-model="goToPageInput"
                  type="number"
                  min="1"
                  :max="pagination.last_page"
                  class="w-24 border p-2 rounded text-sm"
                  :disabled="isSaving || isLoading"
                  @keydown.enter="goToPage"
                  @blur="goToPage"
              />
            </div>
          </div>
        </div>
      </div>
      <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 flex justify-end">
        <button
            :class="[
            'px-4 py-2 rounded text-white font-medium transition-colors',
            hasChanges && !isSaving && !isLoading ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-400 cursor-not-allowed',
          ]"
            :disabled="!hasChanges || isSaving || isLoading"
            @click="saveAllTranslations"
        >
          {{ $t('save_all') }}
        </button>
      </div>
    </div>
    <UniversalModal :show="showModal" :title="$t('add_new_key')" @close="showModal = false">
      <div class="p-4">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">{{ $t('key') }}</label>
          <input
              v-model="modalKey"
              type="text"
              :placeholder="$t('enter_key')"
              class="mt-1 block w-full border p-2 rounded"
              :disabled="isSaving || isLoading"
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">{{ $t('value') }}</label>
          <input
              v-model="modalValue"
              type="text"
              :placeholder="$t('enter_value')"
              class="mt-1 block w-full border p-2 rounded"
              :disabled="isSaving || isLoading"
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
              class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors"
              @click="showModal = false"
              :disabled="isSaving || isLoading"
          >
            {{ $t('cancel') }}
          </button>
          <button
              class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors"
              @click="addKey"
              :disabled="isSaving || isLoading"
          >
            {{ $t('add') }}
          </button>
        </div>
      </div>
    </UniversalModal>
  </div>
</template>