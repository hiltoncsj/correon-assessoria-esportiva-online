/*
 * Conteúdo da Hero 7 (Resultados, Notícias e Eventos) — ponto único de edição.
 *
 * Regras:
 *  - Nada aqui é inventado: as listas começam VAZIAS e a seção mostra um estado
 *    vazio. Só cadastrar resultados, notícias e eventos reais e confirmados.
 *  - Publicar fotos e resultados de atletas somente com autorização (imagem e
 *    dados pessoais). Não incluir dados privados.
 *  - Resultados só entram quando confirmados como oficiais.
 *  - `href` é opcional: se ausente, o botão de detalhes não aparece (ainda não
 *    existem páginas de detalhe).
 *  - Datas em ISO (AAAA-MM-DD). Evento passado vira "realizado" automaticamente
 *    e continua na lista (o histórico não é apagado). Como o site é estático, a
 *    classificação é calculada na hora do build; um backend/API atualizaria isso
 *    sem republicar.
 *  - Futuro: estas listas virão de uma API (PHP + MariaDB) com painel
 *    administrativo, permissões e agendamento de publicação.
 */
import type { ImageMetadata } from 'astro';

export interface ResultItem {
	id: string;
	athlete: string;
	modality: string; // Corrida, Triathlon, Natação, Ciclismo...
	competition: string;
	date: string;
	distance?: string; // distância ou categoria
	result: string; // tempo ou resultado
	placement?: string;
	text?: string;
	image?: ImageMetadata;
	imageAlt?: string;
	href?: string;
}

export interface NewsItem {
	id: string;
	category: string;
	title: string;
	summary: string;
	date: string;
	image?: ImageMetadata;
	imageAlt?: string;
	href?: string;
}

export type EventStatus = 'proximo' | 'andamento' | 'realizado';

export interface EventItem {
	id: string;
	name: string;
	date: string;
	time?: string;
	place: string;
	modality: string;
	description?: string;
	image?: ImageMetadata;
	imageAlt?: string;
	registrationHref?: string;
	href?: string;
	status?: EventStatus; // se omitido, deriva da data
}

export const results: ResultItem[] = [];
export const news: NewsItem[] = [];
export const events: EventItem[] = [];
