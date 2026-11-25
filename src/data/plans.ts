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

/**
 * Combinações com preço próprio (desconto de combo). Só vale quando TODAS as
 * modalidades da combinação estão no mesmo período e há preço definido nele;
 * caso contrário, soma os preços individuais. Combinações que não existem
 * (Corrida + Triathlon, as três juntas) são impedidas por `exclusiveGroups`.
 * Musculação sozinha usa o preço individual. O combo só tem preço definido no mensal
 * (R$ 200); nos demais períodos a página soma os preços individuais.
 */
export const bundles: { modalities: ModalityId[]; prices: Record<PeriodId, number | null> }[] = [
	{
		modalities: ['corrida', 'musculacao'],
		prices: { mensal: 200, trimestral: null, semestral: null, anual: null },
	},
	{
		modalities: ['triathlon', 'musculacao'],
		prices: { mensal: 200, trimestral: null, semestral: null, anual: null },
	},
];

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
