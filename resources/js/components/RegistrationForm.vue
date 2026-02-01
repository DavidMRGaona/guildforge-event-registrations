<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { CustomField, RegistrationFormData } from '../types/registration';

interface Props {
    customFields: CustomField[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const emit = defineEmits<{
    submit: [data: RegistrationFormData];
    cancel: [];
}>();

const { t } = useI18n();

const formData = ref<Record<string, string>>({});
const notes = ref('');
const errors = ref<Record<string, string>>({});

// Initialize form data with empty values
props.customFields.forEach((field) => {
    formData.value[field.name] = '';
});

const isValid = computed(() => {
    for (const field of props.customFields) {
        if (field.required && !formData.value[field.name]) {
            return false;
        }
    }
    return true;
});

function validate(): boolean {
    errors.value = {};

    for (const field of props.customFields) {
        if (field.required && !formData.value[field.name]) {
            errors.value[field.name] = t('validation.required', { attribute: field.label });
        }
    }

    return Object.keys(errors.value).length === 0;
}

function handleSubmit(): void {
    if (!validate()) return;

    const data: RegistrationFormData = {
        form_data: formData.value,
    };

    if (notes.value) {
        data.notes = notes.value;
    }

    emit('submit', data);
}
</script>

<template>
    <div class="rounded-lg border border-default bg-surface p-4 shadow-sm">
        <h3 class="mb-4 text-lg font-semibold text-base-primary">
            {{ t('eventRegistrations.register') }}
        </h3>

        <form @submit.prevent="handleSubmit">
            <!-- Custom fields -->
            <div class="space-y-4">
                <div
                    v-for="field in customFields"
                    :key="field.name"
                >
                    <label
                        :for="field.name"
                        class="mb-1 block text-sm font-medium text-base-secondary"
                    >
                        {{ field.label }}
                        <span v-if="field.required" class="text-error">*</span>
                    </label>

                    <!-- Text input -->
                    <input
                        v-if="field.type === 'text' || field.type === 'email'"
                        :id="field.name"
                        v-model="formData[field.name]"
                        :type="field.type"
                        :required="field.required"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        :class="{ 'border-error': errors[field.name] }"
                    />

                    <!-- Textarea -->
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        :id="field.name"
                        v-model="formData[field.name]"
                        :required="field.required"
                        rows="3"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        :class="{ 'border-error': errors[field.name] }"
                    ></textarea>

                    <!-- Select -->
                    <select
                        v-else-if="field.type === 'select'"
                        :id="field.name"
                        v-model="formData[field.name]"
                        :required="field.required"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        :class="{ 'border-error': errors[field.name] }"
                    >
                        <option value="">{{ t('common.select') }}...</option>
                        <option
                            v-for="option in field.options"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>

                    <!-- Checkbox -->
                    <div
                        v-else-if="field.type === 'checkbox'"
                        class="flex items-center"
                    >
                        <input
                            :id="field.name"
                            v-model="formData[field.name]"
                            type="checkbox"
                            :required="field.required"
                            class="h-4 w-4 rounded border-default bg-surface text-accent focus:ring-accent"
                        />
                        <label
                            :for="field.name"
                            class="ml-2 text-sm text-base-secondary"
                        >
                            {{ field.label }}
                        </label>
                    </div>

                    <!-- Error message -->
                    <p
                        v-if="errors[field.name]"
                        class="mt-1 text-sm text-error"
                    >
                        {{ errors[field.name] }}
                    </p>
                </div>

                <!-- Notes field -->
                <div>
                    <label
                        for="notes"
                        class="mb-1 block text-sm font-medium text-base-secondary"
                    >
                        {{ t('eventRegistrations.fields.notes') }}
                    </label>
                    <textarea
                        id="notes"
                        v-model="notes"
                        rows="2"
                        maxlength="500"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        :placeholder="t('eventRegistrations.fields.notes') + '...'"
                    ></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-default px-4 py-2 text-sm font-medium text-base-secondary hover:bg-surface-hover"
                    @click="emit('cancel')"
                >
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="!isValid || loading"
                    class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover disabled:cursor-not-allowed disabled:bg-base-muted disabled:text-base-secondary"
                >
                    <span v-if="loading" class="flex items-center">
                        <svg
                            class="mr-2 h-4 w-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ t('common.loading') }}
                    </span>
                    <span v-else>{{ t('eventRegistrations.register') }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
