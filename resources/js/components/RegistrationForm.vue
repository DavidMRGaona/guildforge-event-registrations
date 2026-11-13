<script setup lang="ts">
import { FormCheckbox, FormSelect } from '@/components/form';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
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

/** A ticked box is sent as text, the only kind of value the server accepts per field. */
const CHECKED = '1';

function isChecked(name: string): boolean {
    return formData.value[name] === CHECKED;
}

function setChecked(name: string, checked: boolean): void {
    formData.value[name] = checked ? CHECKED : '';
}

function setChoice(name: string, value: string | number | null): void {
    formData.value[name] = value === null ? '' : String(value);
}

function optionsOf(field: CustomField): { value: string; label: string }[] {
    return (field.options ?? []).map((option) => ({ value: option, label: option }));
}

function errorIdOf(name: string): string {
    return `${name}-error`;
}

function validate(): boolean {
    errors.value = {};

    for (const field of props.customFields) {
        if (field.required && !formData.value[field.name]) {
            errors.value[field.name] = t('eventRegistrationsForm.required');
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
                <div v-for="field in customFields" :key="field.name">
                    <label
                        v-if="field.type !== 'checkbox'"
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
                        :aria-invalid="errors[field.name] ? 'true' : undefined"
                        :aria-describedby="errors[field.name] ? errorIdOf(field.name) : undefined"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        :class="{ 'border-error': errors[field.name] }"
                    />

                    <!-- Textarea -->
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        :id="field.name"
                        v-model="formData[field.name]"
                        :required="field.required"
                        :aria-invalid="errors[field.name] ? 'true' : undefined"
                        :aria-describedby="errors[field.name] ? errorIdOf(field.name) : undefined"
                        rows="3"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-base-primary focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent"
                        :class="{ 'border-error': errors[field.name] }"
                    ></textarea>

                    <!-- Select -->
                    <FormSelect
                        v-else-if="field.type === 'select'"
                        :id="field.name"
                        :model-value="formData[field.name] ?? ''"
                        :options="optionsOf(field)"
                        :placeholder="`${t('common.select')}...`"
                        :error="errors[field.name] ?? ''"
                        :described-by="errors[field.name] ? errorIdOf(field.name) : undefined"
                        @update:model-value="setChoice(field.name, $event)"
                    />

                    <!-- Checkbox: its own label, on a 44 px target -->
                    <div
                        v-else-if="field.type === 'checkbox'"
                        class="flex min-h-[44px] items-center"
                    >
                        <FormCheckbox
                            :id="field.name"
                            :model-value="isChecked(field.name)"
                            @update:model-value="setChecked(field.name, $event)"
                        >
                            {{ field.label }}
                            <span v-if="field.required" class="text-error">*</span>
                        </FormCheckbox>
                    </div>

                    <!-- Error message -->
                    <p
                        v-if="errors[field.name]"
                        :id="errorIdOf(field.name)"
                        class="mt-1 text-sm text-error"
                    >
                        {{ errors[field.name] }}
                    </p>
                </div>

                <!-- Notes field -->
                <div>
                    <label for="notes" class="mb-1 block text-sm font-medium text-base-secondary">
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
                        <LoadingSpinner class="mr-2" size="sm" color="text-current" />
                        {{ t('common.loading') }}
                    </span>
                    <span v-else>{{ t('eventRegistrations.register') }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
