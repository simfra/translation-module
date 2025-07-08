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

const showImportModal = ref(false);
const importLang = ref('');
const importPrefix = ref('');
const importFile = ref<File | null>(null);

const showSummaryModal = ref(false);
const summaryMessage = ref('');

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
      errorMessage.value = proxy?.$t('translations.no_translations_found') || 'No translations found';
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || proxy?.$t('translations.error_loading_data') || 'Error loading data';
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
  localStorage.setItem('selectedGroup', selectedGroup.value);
  localStorage.setItem('showMissing', String(showMissing.value));
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
    push.error({ message: proxy?.$t('pagination.invalid_page_number') || 'Invalid page number' });
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
    push.error({ message: proxy?.$t('translations.translation_not_found') || 'Translation not found' });
    return;
  }
  if (translation.readonly) {
    push.error({ message: proxy?.$t('translations.cannot_delete_system_translation') || 'Cannot delete system translation' });
    return;
  }
  if (!translation.value.trim()) {
    push.error({ message: proxy?.$t('translations.cannot_delete_empty_translation') || 'Cannot delete empty translation' });
    return;
  }
  if (!translation.id) {
    push.error({ message: proxy?.$t('translations.translation_not_found') || 'Translation not found' });
    return;
  }

  try {
    isSaving.value = true;
    const response = await axios.delete(route('translations.destroy', { id: translation.id }));
    if (!response.data.success) {
      throw new Error(response.data.message || 'translations.error_deleting_translation');
    }
    translations.value = { ...translations.value };
    delete translations.value[key];
    Object.assign(localTranslations.value, { [key]: '' });
    Object.assign(originalTranslations.value, { [key]: '' });
    allKeys.value = allKeys.value.filter(k => k !== key);
    push.success({ message: proxy?.$t(response.data.message || 'success.deleted') || 'Translation deleted' });
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    const errorMsg = errors.id ? errors.id[0] : proxy?.$t(error.message || 'translations.error_deleting_translation') || 'Error deleting translation';
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
      throw new Error(response.data.message || 'translations.error_saving_translations');
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
    const errorMsg = errors.translations ? errors.translations[0] : proxy?.$t(error.message || 'translations.error_saving_translations') || 'Error saving translations';
    push.error({ message: errorMsg });
  } finally {
    isSaving.value = false;
  }
};

const openImportModal = () => {
  if (!fileInput.value?.files?.length) return;
  importFile.value = fileInput.value.files[0];
  importLang.value = selectedLang.value;
  importPrefix.value = importFile.value.name.replace(/\.[^/.]+$/, '');
  showImportModal.value = true;
  fileInput.value.value = '';
};

const importTranslations = async () => {
  if (!importFile.value) return;

  const formData = new FormData();
  formData.append('lang', importLang.value);
  formData.append('file', importFile.value);
  formData.append('prefix', importPrefix.value.trim());

  try {
    isSaving.value = true;
    const response = await axios.post(route('translations.import'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (!response.data.success) {
      throw new Error(response.data.message || 'error.error_importing');
    }
    showImportModal.value = false;
    importFile.value = null;
    push.success({ message: proxy?.$t(response.data.message || 'success.imported') || 'Translations imported' });
    errorMessage.value = null;
    summaryMessage.value = proxy?.$t('translations.import_summary', {
      success_count: response.data.success_count,
      skipped_count: response.data.skipped_count,
    }) || `Imported ${response.data.success_count} translations successfully, ${response.data.skipped_count} skipped.`;
    showSummaryModal.value = true;
    fetchTranslations();
  } catch (error) {
    const errors = error.response?.data?.errors || {};
    const errorMsg = errors.file ? errors.file[0] : proxy?.$t(error.message || 'error.error_importing') || 'Error importing translations';
    push.error({ message: errorMsg });
  } finally {
    isSaving.value = false;
  }
};

const saveFiltersToLocalStorage = () => {
  localStorage.setItem('searchQuery', searchQuery.value);
  localStorage.setItem('selectedGroup', selectedGroup.value);
  localStorage.setItem('showMissing', String(showMissing.value));
  localStorage.setItem('perPage', String(pagination.value.per_page));
};

const loadFiltersFromLocalStorage = () => {
  const savedLang = localStorage.getItem('selectedLang');
  if (savedLang) {
    selectedLang.value = savedLang;
  }
  const savedSearchQuery = localStorage.getItem('searchQuery');
  if (savedSearchQuery) {
    searchQuery.value = savedSearchQuery;
  }
  const savedGroup = localStorage.getItem('selectedGroup');
  if (savedGroup) {
    selectedGroup.value = savedGroup;
  }
  const savedShowMissing = localStorage.getItem('showMissing');
  if (savedShowMissing) {
    showMissing.value = savedShowMissing === 'true';
  }
  const savedPerPage = localStorage.getItem('perPage');
  if (savedPerPage) {
    pagination.value.per_page = parseInt(savedPerPage, 10);
  }
};

onMounted(() => {
  loadFiltersFromLocalStorage();
  fetchTranslations();
});
</script>

<template>
  <Head title="Tłumaczenia" />
  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <h1 class="text-2xl font-bold mb-4">{{ $t('translations.title') }}</h1>
    <div v-if="isLoading && langs.length === 0" class="text-center p-4">
      <svg class="animate-spin h-8 w-8 text-blue-500 mx-auto" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
      </svg>
      <p class="mt-2 text-gray-700">{{ $t('translations.loading') }}</p>
    </div>
    <div v-else-if="langs.length === 0" class="text-center p-4 text-red-500">
      {{ $t('translations.no_languages_available') }}
    </div>
    <div v-else>
      <div class="flex gap-4 mb-4 flex-wrap">
        <select
            v-model="selectedLang"
            @change="changeLanguage"
            class="border p-2 rounded w-48 h-10"
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
              accept=".json,.php"
              class="hidden"
              @change="openImportModal"
              :disabled="isSaving || isLoading"
          />
          <button
              class="h-10 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors"
              @click="fileInput?.click()"
              :disabled="isSaving || isLoading"
          >
            {{ $t('translations.import_file') }}
          </button>
        </div>
        <button
            class="h-10 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors"
            @click="openAddKeyModal"
            :disabled="isSaving || isLoading"
        >
          {{ $t('translations.add_key') }}
        </button>
        <button
            class="h-10 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors"
            @click="fetchTranslations"
            :disabled="isSaving || isLoading"
        >
          {{ $t('translations.refresh') }}
        </button>
        <p v-if="keyError" class="text-red-500 text-sm">{{ keyError }}</p>
      </div>
      <div class="relative min-h-[calc(100vh-200px)] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <div class="p-4 pb-20">
          <div class="mb-4 flex gap-4 flex-wrap">
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('translations.search') }}</label>
              <input
                  ref="searchInput"
                  v-model="searchQuery"
                  type="text"
                  :placeholder="$t('translations.search')"
                  class="mt-1 block w-full max-w-md border p-2 rounded h-10"
                  :disabled="isSaving || isLoading || allKeys.length === 0"
                  @input="debouncedSearch(() => { pagination.current_page = 1; saveFiltersToLocalStorage(); fetchTranslations(); })"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('translations.select_group') }}</label>
              <select
                  v-model="selectedGroup"
                  class="mt-1 block w-full max-w-md border p-2 rounded h-10"
                  :disabled="isSaving || isLoading"
                  @change="pagination.current_page = 1; saveFiltersToLocalStorage(); fetchTranslations();"
              >
                <option v-for="group in groups" :key="group" :value="group">
                  {{ group === 'all' ? $t('translations.all_groups') : group === 'pozostałe' ? $t('translations.other_groups') : group }}
                </option>
              </select>
            </div>
            <div class="flex items-center">
              <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                <input
                    v-model="showMissing"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    :disabled="isSaving || isLoading"
                    @change="pagination.current_page = 1; saveFiltersToLocalStorage(); fetchTranslations();"
                />
                {{ $t('translations.show_missing_translations') }}
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
                class="h-10 mt-2 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors"
                @click="openAddKeyModal"
                :disabled="isSaving || isLoading"
            >
              {{ $t('translations.add_key') }}
            </button>
          </div>
          <table v-else class="min-w-full border">
            <thead>
            <tr>
              <th class="py-2 px-4 border text-left">{{ $t('translations.key') }}</th>
              <th class="py-2 px-4 border text-left">{{ $t('translations.value') }}</th>
              <th class="py-2 px-4 border text-left">{{ $t('translations.actions') }}</th>
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
                          'w-full p-2 rounded border h-10',
                          !translations[key]?.value ? 'bg-red-100 border-red-300' : 'border-gray-400',
                        ]"
                      :disabled="isSaving || isLoading"
                      :title="translations[key]?.readonly ? $t('translations.system_translation') : ''"
                  />
                  <span
                      v-if="!translations[key]?.value"
                      class="absolute right-2 top-1/2 transform -translate-y-1/2 text-red-500"
                      :title="$t('translations.translation_missing')"
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
                    class="h-10 bg-transparent p-2"
                    :class="{
                        'text-green-500 hover:text-green-700': localTranslations[key] !== originalTranslations[key] && !isSaving && !isLoading,
                        'text-gray-500 opacity-50 cursor-not-allowed': localTranslations[key] === originalTranslations[key] || isSaving || isLoading,
                      }"
                    :title="$t('translations.save')"
                    @click="saveTranslation(key)"
                    :disabled="localTranslations[key] === originalTranslations[key] || isSaving || isLoading"
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
                    class="h-10 bg-transparent p-2 text-red-500"
                    :class="{
                        'hover:text-red-700': translations[key]?.value && !translations[key]?.readonly && !isSaving && !isLoading,
                        'opacity-50 cursor-not-allowed': !translations[key]?.value || translations[key]?.readonly || isSaving || isLoading,
                      }"
                    :disabled="!translations[key]?.value || translations[key]?.readonly || isSaving || isLoading"
                    :title="translations[key]?.readonly ? $t('translations.cannot_delete_system_translation') : !translations[key]?.value ? $t('translations.cannot_delete_empty_translation') : $t('translations.delete')"
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
                  class="h-10 px-3 py-2 rounded font-medium transition-colors"
                  :class="[
                    step.active ? 'bg-blue-500 text-white' : step.disabled ? 'bg-gray-200 cursor-not-allowed text-gray-500' : 'bg-gray-200 hover:bg-gray-300 text-gray-700',
                  ]"
                  :disabled="step.disabled || isSaving || isLoading"
                  @click="changePage(step.value)"
              >
                {{ step.label }}
              </button>
            </div>
            <div class="flex items-center gap-2 ml-auto">
              <label class="text-sm font-medium text-gray-700">{{ $t('translations.go_to_page') }}</label>
              <div class="flex items-center gap-2">
                <input
                    v-model="goToPageInput"
                    type="number"
                    min="1"
                    :max="pagination.last_page"
                    class="h-10 w-24 border p-2 rounded"
                    :disabled="isSaving || isLoading"
                    @keydown.enter="goToPage"
                    @blur="goToPage"
                />
                <button
                    class="h-10 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition-colors"
                    @click="goToPage"
                    :disabled="isSaving || isLoading"
                >
                  {{ $t('translations.go') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 flex justify-end">
        <button
            class="h-10 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors"
            :class="{ 'bg-gray-400 cursor-not-allowed': !hasChanges || isSaving || isLoading }"
            :disabled="!hasChanges || isSaving || isLoading"
            @click="saveAllTranslations"
        >
          {{ $t('translations.save_all') }}
        </button>
      </div>
    </div>
    <UniversalModal :show="showModal" :title="$t('translations.add_new_key')" @close="showModal = false">
      <div class="p-4">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">{{ $t('translations.key') }}</label>
          <input
              v-model="modalKey"
              type="text"
              :placeholder="$t('translations.enter_key')"
              class="mt-1 block w-full border p-2 rounded h-10"
              :disabled="isSaving || isLoading"
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">{{ $t('translations.value') }}</label>
          <input
              v-model="modalValue"
              type="text"
              :placeholder="$t('translations.enter_value')"
              class="mt-1 block w-full border p-2 rounded h-10"
              :disabled="isSaving || isLoading"
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
              class="h-10 bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors"
              @click="showModal = false"
              :disabled="isSaving || isLoading"
          >
            {{ $t('translations.cancel') }}
          </button>
          <button
              class="h-10 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors"
              @click="addKey"
              :disabled="isSaving || isLoading"
          >
            {{ $t('translations.add') }}
          </button>
        </div>
      </div>
    </UniversalModal>
    <UniversalModal :show="showImportModal" :title="$t('translations.import_translations')" @close="showImportModal = false; importFile = null">
      <div class="p-4">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">{{ $t('translations.language') }}</label>
          <select
              v-model="importLang"
              class="mt-1 block w-full border p-2 rounded h-10"
              :disabled="isSaving || isLoading"
          >
            <option v-for="lang in langs" :key="lang.code" :value="lang.code">
              {{ lang.name }}
            </option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">{{ $t('translations.prefix') }}</label>
          <input
              v-model="importPrefix"
              type="text"
              :placeholder="$t('translations.enter_prefix')"
              class="mt-1 block w-full border p-2 rounded h-10"
              :disabled="isSaving || isLoading"
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
              class="h-10 bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition-colors"
              @click="showImportModal = false; importFile = null"
              :disabled="isSaving || isLoading"
          >
            {{ $t('translations.cancel') }}
          </button>
          <button
              class="h-10 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors"
              @click="importTranslations"
              :disabled="isSaving || isLoading || !importLang"
          >
            {{ $t('translations.import') }}
          </button>
        </div>
      </div>
    </UniversalModal>
    <UniversalModal :show="showSummaryModal" :title="$t('translations.import_summary_title')" @close="showSummaryModal = false">
      <div class="p-4">
        <p class="text-gray-700">{{ summaryMessage }}</p>
        <div class="flex justify-end mt-4">
          <button
              class="h-10 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition-colors"
              @click="showSummaryModal = false"
              :disabled="isSaving || isLoading"
          >
            {{ $t('translations.ok') }}
          </button>
        </div>
      </div>
    </UniversalModal>
  </div>
</template>