export type HeroPrecisionConfig = {
	badge: string;
	headline_primary: string;
	headline_accent: string;
	rating_label: string;
	rating_subtext: string;
	stat_2_value: string;
	stat_2_label: string;
	stat_3_value: string;
	stat_3_label: string;
	body: string;
	cta_primary_text: string;
	cta_primary_link: string;
	cta_secondary_text: string;
	cta_secondary_link: string;
	image_desktop: string;
	image_mobile: string;
};

export const HERO_PRECISION_DEFAULTS: HeroPrecisionConfig = {
	badge: 'RESEARCH-GRADE PEPTIDES — USA MANUFACTURED',
	headline_primary: 'The Precision Standard for',
	headline_accent: 'Research Peptides',
	rating_label: 'Excellent',
	rating_subtext: '10,000+ verified researchers',
	stat_2_value: '99%+',
	stat_2_label: 'Guaranteed purity',
	stat_3_value: 'Same-Day',
	stat_3_label: 'USA fulfillment',
	body:
		'Every compound we supply is synthesized in GMP-certified US facilities, verified by HPLC and Mass Spectrometry, and supported by fully transparent Certificates of Analysis — before it ships to your lab.',
	cta_primary_text: 'Shop All Peptides',
	cta_primary_link: '/shop',
	cta_secondary_text: 'View COA Reports',
	cta_secondary_link: '/coa-library',
	image_desktop: '',
	image_mobile: '',
};

export function resolveHeroPrecision(
	raw?: Partial<HeroPrecisionConfig> | null
): HeroPrecisionConfig {
	return { ...HERO_PRECISION_DEFAULTS, ...(raw ?? {}) };
}
