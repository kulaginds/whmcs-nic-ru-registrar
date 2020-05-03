-- phpMyAdmin SQL Dump
-- version 4.7.7
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Мар 19 2018 г., 14:20
-- Версия сервера: 5.6.39-cll-lve
-- Версия PHP: 5.6.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- База данных: `whmcsru_whmcs`
--

-- --------------------------------------------------------

--
-- Структура таблицы `module_nic_ru`
--

CREATE TABLE `module_nic_ru` (
  `id` int(6) NOT NULL,
  `domain_id` int(10) NOT NULL,
  `anketa` text NOT NULL,
  `pass` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Модуль регистрации доменов nic.ru';

-- --------------------------------------------------------

--
-- Структура таблицы `module_nic_ru_anketa`
--

CREATE TABLE `module_nic_ru_anketa` (
  `id` int(10) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `anketa` varchar(15) NOT NULL,
  `pass` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `module_nic_ru`
--
ALTER TABLE `module_nic_ru`
  ADD PRIMARY KEY (`id`),
  ADD KEY `domain_id` (`domain_id`);

--
-- Индексы таблицы `module_nic_ru_anketa`
--
ALTER TABLE `module_nic_ru_anketa`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `module_nic_ru`
--
ALTER TABLE `module_nic_ru`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT для таблицы `module_nic_ru_anketa`
--
ALTER TABLE `module_nic_ru_anketa`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;
