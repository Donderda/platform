import template from './sw-cms-slot.html.twig';
import './sw-cms-slot.scss';

const { Component } = Shopware;

Component.register('sw-cms-slot', {
    template,

    inject: ['cmsService'],

    props: {
        element: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },

        active: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showElementSettings: false,
            showElementSelection: false
        };
    },

    computed: {
        elementConfig() {
            return this.cmsService.getCmsElementConfigByName(this.element.type);
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },

        cmsSlotSettingsClasses() {
            if (this.elementConfig.defaultConfig) {
                return null;
            }
            return 'is--disabled';
        },

        tooltipDisabled() {
            if (this.elementConfig.disabledConfigInfoTextKey) {
                return {
                    message: this.$tc(this.elementConfig.disabledConfigInfoTextKey)
                };
            }
            return {
                message: this.$tc('sw-cms.elements.configTabSettings'),
                disabled: true
            };
        }
    },

    methods: {
        onSettingsButtonClick() {
            if (!this.elementConfig.defaultConfig) {
                return;
            }
            this.showElementSettings = true;
        },

        onCloseSettingsModal() {
            this.showElementSettings = false;
        },

        onElementButtonClick() {
            this.showElementSelection = true;
        },

        onCloseElementModal() {
            this.showElementSelection = false;
        },

        onSelectElement(elementType) {
            this.element.data = {};
            this.element.config = {};
            this.element.type = elementType;
            this.showElementSelection = false;
        }
    }
});
