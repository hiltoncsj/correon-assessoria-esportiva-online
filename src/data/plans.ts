/*
 * Configuração comercial da Hero 6 — ponto único para preços.
 *
 * Regras:
 *  - `null` = preço ainda não definido pela administração. A interface mostra
 *    "Em breve" e NÃO permite contratar esse período.
 *  - Preços para NOVOS alunos, por modalidade (valor TOTAL do período): mensal R$ 180,
 *    trimestral R$ 480 (até 3x), semestral R$ 840 (até 6x), anual R$ 1.440 (até 12x).
 *  - Não afirmar "sem juros": juros do parcelamento ainda não foram definidos.
 *  - O preço legado de alunos antigos NÃO fica neste arquivo (é público no
 *    navegador). Ele pertence ao backend/cadastro, que ainda não existe.
 *  - Estes valores são só de exibição. A validação final do preço
 *    deve ser feita no servidor.
 */

export type ModalityId = 'corrida' | 'triathlon' | 'musculacao';
export type PeriodId = 'mensal' | 'trimestral' | 'semestral' | 'anual';

export const periods: { id: PeriodId; label: string }[] = [
	{ id: 'mensal', label: 'Mensal' },
	{ id: 'trimestral', label: 'Trimestral' },
	{ id: 'semestral', label: 'Semestral' },
	{ id: 'anual', label: 'Anual' },
];

export const modalities: { id: ModalityId; name: string; description: string }[] = [
	{
		id: 'corrida',
		name: 'Corrida',
		description:
			'Treinamento personalizado para quem deseja começar a correr, ganhar consistência ou superar seus limites.',
	},
	{
		id: 'triathlon',
		name: 'Triathlon',
		description: 'Planejamento integrado para evolução na natação, ciclismo e corrida.',
	},
	{
		id: 'musculacao',
		name: 'Musculação',
		description: 'Treinamento estruturado para desenvolver força, condicionamento e desempenho.',
	},
];

/** Número máximo de parcelas de cada período (1 = à vista). */
export const installments: Record<PeriodId, number> = { mensal: 1, trimestral: 3, semestral: 6, anual: 12 };

/** Preço TOTAL em reais por modalidade e período (novos alunos). */
export const prices: Record<ModalityId, Record<PeriodId, number | null>> = {
	corrida: { mensal: 180, trimestral: 480, semestral: 840, anual: 1440 },
	triathlon: { mensal: 180, trimestral: 480, semestral: 840, anual: 1440 },
	musculacao: { mensal: 180, trimestral: 480, semestral: 840, anual: 1440 },
};

/**
 * Grupos excludentes: só uma modalidade de cada grupo pode estar selecionada.
 * Corrida e Triathlon não se combinam (é uma ou outra).
 */
export const exclusiveGroups: ModalityId[][] = [['corrida', 'triathlon']];

/** Desconto (%) sobre a soma dos preços individuais quando as modalidades são combinadas. */
export const bundleDiscountPct = 15;

/** Meses de cada período (base do desconto em relação ao mensal). */
export const months: Record<PeriodId, number> = { mensal: 1, trimestral: 3, semestral: 6, anual: 12 };

/**
 * Combinações com desconto: Corrida + Musculação e Triathlon + Musculação. O preço é a soma dos
 * individuais menos `bundleDiscountPct`, quando as duas modalidades estão no MESMO período.
 * Combinações que não existem (Corrida + Triathlon, as três juntas) são impedidas por `exclusiveGroups`.
 */
const combos: ModalityId[][] = [
	['corrida', 'musculacao'],
	['triathlon', 'musculacao'],
];
export const bundles: { modalities: ModalityId[]; prices: Record<PeriodId, number | null> }[] = combos.map((mods) => ({
	modalities: mods,
	prices: Object.fromEntries(
		periods.map((p) => {
			const singles = mods.map((m) => prices[m][p.id]);
			const sum = singles.every((v) => v !== null) ? (singles as number[]).reduce((a, v) => a + v, 0) : null;
			return [p.id, sum === null ? null : Math.round(sum * (100 - bundleDiscountPct)) / 100];
		}),
	) as Record<PeriodId, number | null>,
}));

/** Benefícios incluídos nos três produtos digitais (não acrescentar outros). */
export const benefits = [
	'Planilha de treinamento personalizada',
	'Acesso à plataforma CorreON',
	'Acompanhamento do treinador',
];

/** Formatos comerciais do Personal Trainer (valores ainda não cadastrados). */
export const personalFormats = [
	{ id: 'pacotes', label: 'Pacotes de sessões' },
	{ id: 'mensalidade', label: 'Mensalidade com frequência semanal definida' },
	{ id: 'avulsa', label: 'Aula avulsa' },
];
