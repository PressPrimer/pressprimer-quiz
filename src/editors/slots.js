/**
 * Editor slots
 *
 * Addons add panels to the quiz, question and bank editors by registering
 * React components against named slots:
 *
 *     window.PPQ.editors.registerSlot( 'quiz.premium.card', {
 *         key: 'my-card',
 *         surfaces: [ 'admin', 'dashboard' ], // default [ 'admin' ]
 *         Component: MyCard,
 *     } );
 *
 * The registry lives on window.PPQ.editors, so every bundle on the page (each
 * wp-admin editor, the dashboard editors, addon scripts) shares one store.
 * Registrations made before an editor mounts are kept; a later registration
 * re-renders mounted editors. Free renders each slot inside its own error
 * boundary and passes it explicit props, because Ant Design's Form context
 * doesn't cross bundles.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { Component, useState, useEffect, useRef, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Alert } from 'antd';
import { debugError, debugLog } from '../utils/debug';
import { getPpqTheme } from '../utils/ppqTheme';

/** The slot ids an addon can register against. */
export const SLOT_IDS = [
	'quiz.tab',
	'quiz.premium.card',
	'question.tab',
	'question.answers.actions',
	'bank.fields',
];

const SURFACES = ['admin', 'dashboard'];

/**
 * Normalize a slot definition, or null when it can't be rendered.
 *
 * @param {string} slotId Slot id.
 * @param {Object} def    Definition from registerSlot() or the editor's slots prop.
 * @param {number} seq    Registration order, for ties.
 * @return {Object|null} Normalized definition.
 */
const normalizeDef = (slotId, def, seq) => {
	if (!SLOT_IDS.includes(slotId)) {
		debugLog('registerSlot: unknown slot id', slotId);
		return null;
	}
	if (!def || typeof def !== 'object' || typeof def.key !== 'string' || '' === def.key) {
		debugLog('registerSlot: a slot needs a key', slotId);
		return null;
	}

	const RenderedComponent = def.Component;
	if (!RenderedComponent || ('function' !== typeof RenderedComponent && 'object' !== typeof RenderedComponent)) {
		debugLog('registerSlot: a slot needs a Component', slotId, def.key);
		return null;
	}

	const order = Number(def.order);

	return {
		slotId,
		key: def.key,
		order: Number.isFinite(order) ? order : 10,
		label: typeof def.label === 'string' ? def.label : '',
		surfaces: Array.isArray(def.surfaces) ? def.surfaces.filter((surface) => SURFACES.includes(surface)) : ['admin'],
		capability: typeof def.capability === 'string' ? def.capability : '',
		isVisible: typeof def.isVisible === 'function' ? def.isVisible : null,
		Component: RenderedComponent,
		seq,
	};
};

/**
 * Create the slot store.
 *
 * @return {Object} register, list, subscribe, version.
 */
const createRegistry = () => {
	const slots = new Map(SLOT_IDS.map((slotId) => [slotId, new Map()]));
	const listeners = new Set();
	let version = 0;
	let seq = 0;

	return {
		register(slotId, def) {
			const existing = slots.has(slotId) ? slots.get(slotId).get(def?.key) : null;
			const normalized = normalizeDef(slotId, def, existing ? existing.seq : ++seq);
			if (!normalized) {
				return false;
			}

			// The same key again replaces the definition in place.
			slots.get(slotId).set(normalized.key, normalized);
			version++;
			listeners.forEach((listener) => listener());
			return true;
		},
		list(slotId) {
			return slots.has(slotId) ? Array.from(slots.get(slotId).values()) : [];
		},
		subscribe(listener) {
			listeners.add(listener);
			return () => listeners.delete(listener);
		},
		version() {
			return version;
		},
	};
};

/**
 * The shared slot store, created on first use, with
 * window.PPQ.editors.registerSlot() pointing at it.
 *
 * Each editor entry calls this when its bundle loads, so registerSlot()
 * exists before the editor mounts and before addon scripts that depend on
 * the editor's handle run.
 *
 * @return {Object} The slot store.
 */
export const getSlotRegistry = () => {
	window.PPQ = window.PPQ || {};
	window.PPQ.editors = window.PPQ.editors || {};
	const editors = window.PPQ.editors;

	if (!editors._slotRegistry) {
		Object.defineProperty(editors, '_slotRegistry', { value: createRegistry() });
	}
	if (typeof editors.registerSlot !== 'function') {
		/**
		 * Register an editor slot.
		 *
		 * @param {string} slotId One of SLOT_IDS.
		 * @param {Object} def    { key, order, label, surfaces, capability, isVisible, Component }.
		 * @return {boolean} False when the definition was refused.
		 */
		editors.registerSlot = (slotId, def) => editors._slotRegistry.register(slotId, def);
	}

	return editors._slotRegistry;
};

/**
 * Re-render when a slot registers.
 *
 * Call once in each editor. Catches a registration made between the
 * editor's first render and its subscribing.
 */
export const useSlotRegistry = () => {
	const registry = getSlotRegistry();
	const [, setVersion] = useState(registry.version());
	const renderedVersion = useRef(registry.version());
	renderedVersion.current = registry.version();

	useEffect(() => {
		const unsubscribe = registry.subscribe(() => setVersion(registry.version()));
		if (registry.version() !== renderedVersion.current) {
			setVersion(registry.version());
		}
		return unsubscribe;
	}, [registry]);
};

/**
 * Whether the user has a slot's capability, from the editor's site flags.
 *
 * Accepts the plugin's capabilities ('pressprimer_quiz_manage_all' or
 * 'manage_all'), 'manage_options' and 'pressprimer_quiz_manage_settings',
 * and any key an addon adds to the editor data's userCan. Anything else is
 * treated as not held. The server still gates the data.
 *
 * @param {string} capability Capability, or '' for none.
 * @param {Object} site       Site flags (getSiteFlags()).
 * @return {boolean} True when the slot may render.
 */
export const userHasCapability = (capability, site = {}) => {
	if (!capability) {
		return true;
	}
	if ('manage_options' === capability) {
		return !!site.isAdmin;
	}
	if ('pressprimer_quiz_manage_settings' === capability) {
		return !!site.canManageSettings;
	}

	const userCan = site.userCan || {};
	const short = capability.replace(/^pressprimer_quiz_/, '');
	return !!(userCan[capability] || userCan[short]);
};

/**
 * Every definition for a slot: registered ones plus the host's.
 *
 * @param {string} slotId    Slot id.
 * @param {Object} hostSlots The editor's slots prop ({ [slotId]: def[] }).
 * @return {Object[]} Normalized definitions; a host definition replaces a registered one with its key.
 */
const allDefs = (slotId, hostSlots = {}) => {
	const byKey = new Map(getSlotRegistry().list(slotId).map((def) => [def.key, def]));
	const fromHost = hostSlots && Array.isArray(hostSlots[slotId]) ? hostSlots[slotId] : [];

	fromHost.forEach((def, index) => {
		const normalized = normalizeDef(slotId, def, 1000000 + index);
		if (normalized) {
			byKey.set(normalized.key, normalized);
		}
	});

	return Array.from(byKey.values());
};

/**
 * The definitions that render here, in order.
 *
 * isVisible is called on every render, so it's re-checked when the entity
 * or the generation mode changes.
 *
 * @param {string} slotId             Slot id.
 * @param {Object} ctx                Render context.
 * @param {string} ctx.surface        'admin' or 'dashboard'.
 * @param {Object} ctx.site           Site flags (getSiteFlags()).
 * @param {Object} ctx.hostSlots      The editor's slots prop.
 * @param {number} ctx.entityId       The entity's ID (0 for new).
 * @param {string} ctx.generationMode The quiz's generation mode (quiz slots).
 * @return {Object[]} Definitions to render.
 */
export const getVisibleSlots = (slotId, { surface, site, hostSlots, entityId, generationMode }) =>
	allDefs(slotId, hostSlots)
		.filter((def) => def.surfaces.includes(surface))
		.filter((def) => userHasCapability(def.capability, site))
		.filter((def) => {
			if (!def.isVisible) {
				return true;
			}
			try {
				return !!def.isVisible({ entityId, generationMode, surface });
			} catch (error) {
				debugError('Editor slot isVisible failed:', def.key, error);
				return false;
			}
		})
		.sort((a, b) => a.order - b.order || a.seq - b.seq);

/**
 * Whether a slot with this key is registered for the surface, visible or not.
 *
 * Free's built-in cards step aside when an addon registers its own
 * replacement (e.g. 'educator-prepost').
 *
 * @param {string} slotId    Slot id.
 * @param {string} key       Slot key.
 * @param {string} surface   'admin' or 'dashboard'.
 * @param {Object} hostSlots The editor's slots prop.
 * @return {boolean} True when registered.
 */
export const hasSlotKey = (slotId, key, surface, hostSlots) =>
	allDefs(slotId, hostSlots).some((def) => def.key === key && def.surfaces.includes(surface));

/**
 * Error boundary around one slot, so an addon's error can't take the editor down.
 */
class SlotErrorBoundary extends Component {
	constructor(props) {
		super(props);
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	componentDidCatch(error) {
		debugError('Editor slot failed:', this.props.slotKey, error);
	}

	render() {
		if (this.state.failed) {
			return (
				<Alert
					type="warning"
					showIcon
					message={__('An add-on panel couldn’t be shown.', 'pressprimer-quiz')}
				/>
			);
		}
		return this.props.children;
	}
}

/**
 * Render one slot.
 *
 * @param {Object} props           Component props.
 * @param {Object} props.def       Normalized definition.
 * @param {Object} props.slotProps Props for the slot's component.
 * @return {JSX.Element} The slot.
 */
export const SlotOutlet = ({ def, slotProps }) => (
	<div className="ppq-editor-slot" data-slot={def.slotId} data-slot-key={def.key}>
		<SlotErrorBoundary slotKey={def.key}>
			<def.Component {...slotProps} />
		</SlotErrorBoundary>
	</div>
);

/**
 * Render a slot's definitions in order.
 *
 * @param {Object}   props           Component props.
 * @param {Object[]} props.defs      Definitions (getVisibleSlots()).
 * @param {Object}   props.slotProps Props for each slot's component.
 * @return {JSX.Element[]} The slots.
 */
export const SlotList = ({ defs, slotProps }) =>
	defs.map((def) => <SlotOutlet key={`${def.slotId}:${def.key}`} def={def} slotProps={slotProps} />);

/**
 * The editor side of the slot props: values slots set, their validators and
 * their after-save hooks. Stable for the editor's lifetime.
 *
 * @param {Object} form Ant Design form instance.
 * @return {Object} setFieldValue, markSet, registerValidator, registerAfterSave,
 *                  runValidators, runAfterSave, setValues.
 */
export const useSlotHost = (form) => {
	const host = useRef(null);

	if (!host.current) {
		const validators = new Set();
		const afterSave = new Set();
		const setKeys = new Set();

		const register = (set, fn) => {
			if (typeof fn !== 'function') {
				return () => {};
			}
			set.add(fn);
			return () => set.delete(fn);
		};

		host.current = {
			/**
			 * Set a form value from a slot. It's sent with the next save, even
			 * when no field of that name is on screen.
			 *
			 * @param {string|Array} name  Field name or path.
			 * @param {*}            value Value.
			 */
			setFieldValue(name, value) {
				form.setFieldValue(name, value);
				setKeys.add(Array.isArray(name) ? name[0] : name);
			},
			/**
			 * Send these fields with the next save (e.g. a template set them).
			 *
			 * @param {string[]} keys Field names.
			 */
			markSet(keys) {
				keys.forEach((key) => setKeys.add(key));
			},
			registerValidator(fn) {
				return register(validators, fn);
			},
			registerAfterSave(fn) {
				return register(afterSave, fn);
			},
			/**
			 * Run the slots' validators.
			 *
			 * @param {Object} ctx { entityId, values }.
			 * @return {Promise<string[]>} Error messages; empty when every validator passed.
			 */
			async runValidators(ctx) {
				const errors = [];
				for (const fn of validators) {
					try {
						const result = await fn(ctx);
						if (result && false === result.valid && Array.isArray(result.errors)) {
							errors.push(...result.errors);
						}
					} catch (error) {
						debugError('Editor slot validator failed:', error);
					}
				}
				return errors;
			},
			/**
			 * Run the slots' after-save hooks and wait for them, so the editor
			 * doesn't navigate away from a request still in flight.
			 *
			 * @param {Object} ctx { entityId, isNew, values, response }.
			 */
			async runAfterSave(ctx) {
				const results = await Promise.allSettled(
					Array.from(afterSave).map((fn) => Promise.resolve().then(() => fn(ctx)))
				);
				results
					.filter((result) => 'rejected' === result.status)
					.forEach((result) => debugError('Editor slot after-save hook failed:', result.reason));
			},
			/**
			 * The values slots (or templates) set, for the save payload.
			 *
			 * @return {Object} Field values keyed by name.
			 */
			setValues() {
				const values = {};
				setKeys.forEach((key) => {
					values[key] = form.getFieldValue(key);
				});
				return values;
			},
		};
	}

	return host.current;
};

/**
 * Popup container and theme for slots.
 *
 * In wp-admin, popups go to document.body and Ant Design's default theme
 * applies, as in free's own editors. On the dashboard they go inside
 * .ppq-shell, so its tokens and scoped styles reach them, and the theme
 * comes from the shell's tokens.
 *
 * @param {string} surface 'admin' or 'dashboard'.
 * @return {Object} rootRef (for the editor container), getPopupContainer, theme.
 */
export const useSlotEnvironment = (surface) => {
	const rootRef = useRef(null);

	return useMemo(() => ({
		rootRef,
		getPopupContainer: () => {
			if ('dashboard' === surface && rootRef.current) {
				return rootRef.current.closest('.ppq-shell') || document.body;
			}
			return document.body;
		},
		theme: 'dashboard' === surface ? getPpqTheme() : {},
	}), [surface]);
};
