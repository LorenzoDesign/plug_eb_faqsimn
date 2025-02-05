<?php
/**
 * @package            Joomla
 * @subpackage         Event Booking
 * @author             Lorenzo Giovannini
 * @copyright          Copyright (C) 2010 - 2023 Ossolution Team
 * @license            GNU/GPL, see LICENSE.php
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;

class TableFaqs extends JTable
{
	public function __construct($db)
	{
		parent::__construct( '#__eb_faqs', 'id', $db );
	}
}


class plgEventBookingFaqs extends CMSPlugin
{
	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
	 */
	protected $db;

	/**
	 * Render setting form
	 *
	 * @param   EventbookingTableEvent  $row
	 *
	 * @return array
	 */
	public function onEditEvent($row)
	{
		if (!$this->canRun($row))
		{
			return;
		}

		return [
			'title' => Text::_('FAQ'),
			'form'  => $this->drawSettingForm($row),
		];
	}

	/**
	 * Store setting into database, in this case, use params field of plans table
	 *
	 * @param   EventbookingTableEvent  $row
	 * @param   bool                    $isNew  true if create new plan, false if edit
	 */
	public function onAfterSaveEvent($row, $data, $isNew)
	{
		if (!$this->canRun($row))
		{
			return;
		}

		$db    = $this->db;
		$query = $db->getQuery(true);

		$faqs = isset($data['faqs']) && is_array($data['faqs']) ? $data['faqs'] : [];

		$faqIds = [];
		$ordering   = 1;
		
		function getTable($type = 'Faqs', $prefix = 'Table', $config = array())
		{
			return JTable::getInstance($type, $prefix, $config);
		}		

		//$test = JTable::getInstance('Faqs', 'Table', array());

		foreach ($faqs as $faq)
		{
			/* @var EventbookingTableSpeaker $rowFaq */
			$rowFaq = JTable::getInstance('Faqs', 'Table', array());
			$rowFaq->bind($faq);

			// Prevent faq data being moved to new event on saveAsCopy
			if ($isNew)
			{
				$rowFaq->id = 0;
			}

			$rowFaq->event_id = $row->id;
			$rowFaq->ordering = $ordering++;
			$rowFaq->store();
			$faqIds[] = $rowFaq->id;
		}

		if (!$isNew)
		{
			$query->delete('#__eb_faqs')
				->where('event_id = ' . $row->id);

			if (count($faqIds))
			{
				$query->where('id NOT IN (' . implode(',', $faqIds) . ')');
			}

			$db->setQuery($query)
				->execute();

			$query->clear()
				->delete('#__eb_event_faqs')
				->where('event_id = ' . $row->id);
			$db->setQuery($query);
			$db->execute();
		}

		if (!empty($data['existing_faq_ids']))
		{
			$faqIds = array_filter(ArrayHelper::toInteger($data['existing_faq_ids']));

			if (count($faqIds))
			{
				$query->clear()
					->insert('#__eb_event_faqs')
					->columns($db->quoteName(['event_id', 'faq_id']));

				foreach ($faqIds as $faqId)
				{
					$query->values(implode(',', [$row->id, $faqId]));
				}

				$db->setQuery($query)
					->execute();
			}
		}

		// Insert event faqs into #__eb_event_faqs table
		$sql = 'INSERT INTO #__eb_event_faqs(event_id, faq_id) SELECT event_id, id FROM #__eb_faqs WHERE event_id = ' . $row->id . ' ORDER BY ordering';
		$db->setQuery($sql)
			->execute();
	}

	/**
	 * Display form allows users to change settings on subscription plan add/edit screen
	 *
	 * @param   EventbookingTableEvent  $row
	 *
	 * @return string
	 */
	private function drawSettingForm($row): string
	{

		$xml = simplexml_load_file(JPATH_ROOT . '/plugins/eventbooking/faqs/form/faq.xml');

		if ($this->params->get('use_editor_for_description', 0))
		{
			foreach ($xml->field->form->children() as $field)
			{
				if ($field->attributes()->name == 'risposta')
				{
					//$field->attributes()->type = 'editor';
				}
			}
		}
		$xml->field->attributes()->layout = $this->params->get('subform_layout', 'joomla.form.field.subform.repeatable-table');

		$form             = Form::getInstance('faqs', $xml->asXML());
		$formData['faqs'] = [];
		$selectedfaqIds   = [];

		$db    = $this->db;
		$query = $db->getQuery(true);

		// Load existing faqs for this event
		if ($row->id)
		{
			$query->select('*')
				->from('#__eb_faqs')
				->where('event_id = ' . $row->id)
				->order('ordering');
			$db->setQuery($query);

			foreach ($db->loadObjectList() as $faq)
			{
				$formData['faqs'][] = [
					'id'		=> $faq->id,
					'domanda'	=> $faq->domanda,
					'risposta' 	=> $faq->risposta,
				];
			}

			$query->clear()
				->select('faq_id')
				->from('#__eb_event_faqs')
				->where('event_id = ' . (int) $row->id);
			$db->setQuery($query);
			$selectedfaqIds = $db->loadColumn();
		}

		// Get existing faqs for selection
		$query->clear()
			->select('id, domanda')
			->from('#__eb_faqs')
			->order('ordering');

		if ($row->id)
		{
			$query->where('event_id != ' . $row->id);
		}

		$db->setQuery($query);
		$existingfaqs = $db->loadObjectList();

		// Trigger content plugin
		PluginHelper::importPlugin('content');
		Factory::getApplication()->triggerEvent('onContentPrepareForm', [$form, $formData]);

		$form->bind($formData);

		$layoutData = [
			'existingfaqs'   => $existingfaqs,
			'selectedfaqIds' => $selectedfaqIds,
			'form'           => $form,
		];

		return EventbookingHelperHtml::loadCommonLayout('plugins/faqs_form.php', $layoutData);
	}

	/**
	 * Display event faqs
	 *
	 * @param   EventbookingTableEvent  $row
	 *
	 * @return array|void
	 */
	public function onEventDisplay($row)
	{
		if ($this->params->get('enable_setup_faqs_for_child_event'))
		{
			$eventId = $row->id;
		}
		else
		{
			$eventId = $row->parent_id ?: $row->id;
		}

		$db      = $this->db;
		$query   = $db->getQuery(true)
			->select('a.*')
			->from('#__eb_faqs AS a')
			->innerJoin('#__eb_event_faqs AS b ON a.id = b.faq_id')
			->where('b.event_id = ' . $eventId);

		$query->order('b.id');

		$db->setQuery($query);
		$faqs = $db->loadObjectList();

		if (empty($faqs))
		{
			return;
		}

		return [
			'title'    => Text::_('FAQ Titolo'),
			'form'     => EventbookingHelperHtml::loadCommonLayout('plugins/faqs.php', ['faqs' => $faqs]),
			'position' => $this->params->get('output_position', 'before_register_buttons'),
			'name'     => $this->_name,
		];
	}

	/**
	 * Method to check to see whether the plugin should run
	 *
	 * @param   EventbookingTableEvent  $row
	 *
	 * @return bool
	 */
	private function canRun($row)
	{
		if ($this->app->isClient('site') && !$this->params->get('show_on_frontend'))
		{
			return false;
		}

		if ($row->parent_id > 0 && !$this->params->get('enable_setup_faqs_for_child_event'))
		{
			return false;
		}

		return true;
	}
}
