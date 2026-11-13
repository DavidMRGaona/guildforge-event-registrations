import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { FormCheckbox, FormSelect } from '@/components/form';
import RegistrationForm from '../../resources/js/components/RegistrationForm.vue';
import type { CustomField, RegistrationFormData } from '../../resources/js/types/registration';

const shirtSize: CustomField = {
    name: 'shirt_size',
    label: 'Talla de camiseta',
    type: 'select',
    required: true,
    options: ['S', 'M', 'L'],
};

const photos: CustomField = {
    name: 'photos',
    label: 'Acepto salir en las fotos',
    type: 'checkbox',
    required: true,
};

function mountForm(customFields: CustomField[]): VueWrapper {
    return mount(RegistrationForm, { props: { customFields } });
}

function submitted(form: VueWrapper): RegistrationFormData | undefined {
    return form.emitted<[RegistrationFormData]>('submit')?.at(-1)?.[0];
}

describe('RegistrationForm select field', () => {
    it('uses the site select, named by its label, instead of the browser one', () => {
        const form = mountForm([shirtSize]);

        expect(form.find('select').exists()).toBe(false);
        const select = form.getComponent(FormSelect);
        expect(select.props('options')).toEqual([
            { value: 'S', label: 'S' },
            { value: 'M', label: 'M' },
            { value: 'L', label: 'L' },
        ]);
        expect(select.props('placeholder')).not.toBe('');

        const button = form.get('#shirt_size');
        expect(form.get('label[for="shirt_size"]').text()).toContain('Talla de camiseta');
        expect(button.attributes('aria-invalid')).toBeUndefined();
    });

    it('sends the chosen option', async () => {
        const form = mountForm([shirtSize]);

        await form.getComponent(FormSelect).vm.$emit('update:modelValue', 'M');
        await form.get('form').trigger('submit');

        expect(submitted(form)).toEqual({ form_data: { shirt_size: 'M' } });
    });

    it('links the required error to the select', async () => {
        const form = mountForm([shirtSize]);

        await form.get('form').trigger('submit');

        expect(form.emitted('submit')).toBeUndefined();
        const select = form.getComponent(FormSelect);
        expect(select.props('error')).not.toBe('');
        const button = form.get('#shirt_size');
        expect(button.attributes('aria-invalid')).toBe('true');
        const errorId = button.attributes('aria-describedby');
        expect(errorId).toBeTruthy();
        expect(form.get(`#${errorId}`).text()).toBe('Este campo es obligatorio.');
    });
});

describe('RegistrationForm checkbox field', () => {
    it('uses the site checkbox, named once, with a 44 px target', () => {
        const form = mountForm([photos]);

        const checkbox = form.getComponent(FormCheckbox);
        expect(checkbox.props('id')).toBe('photos');
        expect(form.get('#photos').classes()).toContain('sr-only');
        expect(form.text().split('Acepto salir en las fotos')).toHaveLength(2);
        expect(checkbox.element.parentElement?.className).toContain('min-h-[44px]');
    });

    it('sends text the server accepts once ticked', async () => {
        const form = mountForm([photos]);

        await form.get('#photos').setValue(true);
        await form.get('form').trigger('submit');

        expect(form.getComponent(FormCheckbox).props('modelValue')).toBe(true);
        expect(submitted(form)).toEqual({ form_data: { photos: '1' } });
    });

    it('keeps a required box from being sent unticked', async () => {
        const form = mountForm([photos]);

        await form.get('#photos').setValue(true);
        await form.get('#photos').setValue(false);
        await form.get('form').trigger('submit');

        expect(form.emitted('submit')).toBeUndefined();
        expect(form.text()).toContain('Acepto salir en las fotos');
        expect(form.get('button[type="submit"]').attributes('disabled')).toBeDefined();
    });
});

describe('RegistrationForm text field', () => {
    it('links the required error to the input', async () => {
        const form = mountForm([
            { name: 'nickname', label: 'Apodo', type: 'text', required: true },
        ]);

        await form.get('form').trigger('submit');

        const input = form.get('#nickname');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(form.get(`#${input.attributes('aria-describedby')}`).text()).toBe(
            'Este campo es obligatorio.'
        );
    });
});
