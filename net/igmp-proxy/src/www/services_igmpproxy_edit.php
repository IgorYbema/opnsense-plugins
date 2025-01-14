<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2009 Ermal Luçi
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once('plugins.inc.d/igmpproxy.inc');

$a_igmpproxy = &config_read_array('igmpproxy', 'igmpentry');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_igmpproxy[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    foreach (array('ifname', 'threshold', 'type', 'address', 'whitelist', 'descr') as $fieldname) {
        if (isset($id) && isset($a_igmpproxy[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_igmpproxy[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    $pconfig['networks_network'] = array();
    $pconfig['networks_mask'] = array();
    foreach (explode(" ", $pconfig['address']) as $entry) {
        $parts = explode('/', $entry);
        $pconfig['networks_network'][] = $parts[0];
        $pconfig['networks_mask'][] = $parts[1];
    }
    foreach (explode(" ", $pconfig['whitelist']) as $entry) {
        $parts = explode('/', $entry);
        $pconfig['whitelists_network'][] = $parts[0];
        $pconfig['whitelists_mask'][] = $parts[1];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_igmpproxy[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;
    $input_errors = array();
    $pconfig['address'] = "";
    foreach ($pconfig['networks_network'] as $idx => $value) {
        if (!empty($value) && !empty($pconfig['networks_mask'][$idx])) {
            $pconfig['address'] .= " " . $value . "/" . $pconfig['networks_mask'][$idx];
        }
    }
    $pconfig['address'] = trim($pconfig['address']);
    if ($pconfig['type'] == "upstream") {
        foreach ($a_igmpproxy as $pid => $proxyentry) {
            if (isset($id) && $id == $pid) {
                continue;
            }
            if ($proxyentry['type'] == "upstream" && $proxyentry['ifname'] != $pconfig['interface']) {
                $input_errors[] = gettext("Only one 'upstream' interface can be configured.");
            }
        }
    }
    $pconfig['whitelist'] = "";
    foreach ($pconfig['whitelists_network'] as $idx => $value) {
        if (!empty($value) && !empty($pconfig['whitelists_mask'][$idx])) {
            $pconfig['whitelist'] .= " " . $value . "/" . $pconfig['whitelists_mask'][$idx];
        }
    }
    $pconfig['whitelist'] = trim($pconfig['whitelist']);
    if ($pconfig['type'] == "upstream") {
        foreach ($a_igmpproxy as $pid => $proxyentry) {
            if (isset($id) && $id == $pid) {
                continue;
            }
            if ($proxyentry['type'] == "upstream" && $proxyentry['ifname'] != $pconfig['interface']) {
                $input_errors[] = gettext("Only one 'upstream' interface can be configured.");
            }
        }
    }
    if (count($input_errors) == 0) {
        $igmpentry = array();
        $igmpentry['ifname'] = $pconfig['ifname'];
        $igmpentry['threshold'] = $pconfig['threshold'];
        $igmpentry['type'] = $pconfig['type'];
        $igmpentry['address'] = $pconfig['address'];
        $igmpentry['whitelist'] = $pconfig['whitelist'];
        $igmpentry['descr'] = $pconfig['descr'];

        if (isset($id)) {
            $a_igmpproxy[$id] = $igmpentry;
        } else {
            $a_igmpproxy[] = $igmpentry;
        }

        write_config();
        igmpproxy_configure_do();
        header(url_safe('Location: /services_igmpproxy.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
  <?php include("fbegin.inc"); ?>
  <script>
    $( document ).ready(function() {
      /**
       *  Aliases
       */
      function removeRowNetwork() {
          if ( $('#networks_table > tbody > tr').length == 1 ) {
              $('#networks_table > tbody > tr:last > td > input').each(function(){
                $(this).val("");
              });
          } else {
              $(this).parent().parent().remove();
          }
      }
      function removeRowWhitelist() {
          if ( $('#whitelists_table > tbody > tr').length == 1 ) {
              $('#whitelists_table > tbody > tr:last > td > input').each(function(){
                $(this).val("");
              });
          } else {
              $(this).parent().parent().remove();
          }
      }
      // add new network record
      $("#addNewNetwork").click(function(){
          // copy last row and reset values
          $('#networks_table > tbody').append('<tr>'+$('#networks_table > tbody > tr:last').html()+'</tr>');
          $('#networks_table > tbody > tr:last > td > input').each(function(){
            $(this).val("");
          });
          //  link network / cidr
          var item_cnt = $('#networks_table > tbody > tr').length;
          $('#networks_table > tbody > tr:last > td:eq(1) > input').attr('networkid', 'network_n'+item_cnt);
          $('#networks_table > tbody > tr:last > td:eq(2) > select').data('network-id', 'network_n'+item_cnt);
          $(".act-removerownetwork").click(removeRowNetwork);
          // hookin ipv4/v6 for new item
          hook_ipv4v6('ipv4v6net', 'network-id');
      });
      $(".act-removerownetwork").click(removeRowNetwork);
      // hook in, ipv4/ipv6 selector events
      hook_ipv4v6('ipv4v6net', 'network-id');

      // add new whitelist record
      $("#addNewWhitelist").click(function(){
          // copy last row and reset values
          $('#whitelists_table > tbody').append('<tr>'+$('#whitelists_table > tbody > tr:last').html()+'</tr>');
          $('#whitelists_table > tbody > tr:last > td > input').each(function(){
            $(this).val("");
          });
          //  link network / cidr
          var item_cnt = $('#whitelists_table > tbody > tr').length;
          $('#whitelists_table > tbody > tr:last > td:eq(1) > input').attr('whitelistid', 'whitelist_n'+item_cnt);
          $('#whitelists_table > tbody > tr:last > td:eq(2) > select').data('whitelist-id', 'network_n'+item_cnt);
          $(".act-removerowwhitelist").click(removeRowWhitelist);
          // hookin ipv4/v6 for new item
          hook_ipv4v6('ipv4v6net', 'whitelist-id');
      });
      $(".act-removerowwhitelist").click(removeRowWhitelist);
      // hook in, ipv4/ipv6 selector events
      hook_ipv4v6('ipv4v6net', 'whitelist-id');
    });
  </script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"><strong><?=gettext("IGMP Proxy Edit");?></strong></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface");?></td>
                      <td>
                        <select class="selectpicker" name="ifname" id="ifname" >
<?php
                        foreach (get_configured_interface_with_descr() as $ifnam => $ifdescr):?>
                          <option value="<?=$ifnam;?>" <?=$ifnam == $pconfig['ifname'] ? "selected=\"selected\"" :"";?>>
                            <?=htmlspecialchars($ifdescr);?>
                          </option>

<?php
                        endforeach;?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                      <td>
                        <input name="descr" type="text" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                        <div class="hidden" data-for="help_for_descr">
                          <?=gettext("You may enter a description here for your reference (not parsed).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type");?></td>
                      <td>
                        <select class="selectpicker" name="type" id="type">
                          <option value="upstream" <?=$pconfig['type'] == "upstream" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Upstream Interface");?></option>
                          <option value="downstream" <?= $pconfig['type'] == "downstream" ? "selected=\"selected\"" : ""; ?>><?=gettext("Downstream Interface");?></option>
                        </select>
                        <div class="hidden" data-for="help_for_type">
                            <?=gettext("The upstream network interface is the outgoing interface which is".
                              " responsible for communicating to available multicast data sources.".
                              " There can only be one upstream interface.");?>
                          <br />
                          <?=gettext("Downstream network interfaces are the distribution interfaces to the".
                             " destination networks, where multicast clients can join groups and".
                             " receive multicast data. One or more downstream interfaces must be configured.");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_threshold" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Threshold");?></td>
                      <td>
                        <input name="threshold" type="text" id="threshold" value="<?=$pconfig['threshold'];?>" />
                        <div class="hidden" data-for="help_for_threshold">
                          <?=gettext("Defines the TTL threshold for the network interface. ".
                               "Packets with a lower TTL than the threshold value will be ignored. ".
                               "This setting is optional, and by default the threshold is 1.");?>
                        </div>
                      </td>
                    </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Network(s)");?></td>
                    <td>
                      <table class="table table-striped table-condensed" id="networks_table">
                        <thead>
                          <tr>
                            <th></th>
                            <th><?=gettext("Network"); ?></th>
                            <th><?=gettext("CIDR"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
<?php
                        if (count($pconfig['networks_network']) == 0 ) {
                            $pconfig['networks_network'][] = "";
                            $pconfig['networks_mask'][] = "";
                        }
                        foreach($pconfig['networks_network'] as $item_idx => $network):?>
                          <tr>
                            <td>
                              <div style="cursor:pointer;" class="act-removerownetwork btn btn-default btn-xs" alt="remove"><i class="fa fa-minus fa-fw"></i></div>
                            </td>
                            <td>
                              <input name="networks_network[]" type="text" id="network_<?=$item_idx;?>" value="<?=$network;?>" />
                            </td>
                            <td>
                              <select name="networks_mask[]" data-network-id="network_<?=$item_idx;?>" class="selectpicker ipv4v6net" id="networkmask<?=$item_idx;?>" data-length="3" data-width="auto">
<?php
                                for ($i = 128; $i > 0; $i--):?>
                                <option value="<?=$i;?>" <?= $pconfig['networks_mask'][$item_idx] == $i ?  "selected=\"selected\"" : ""?>>
                                  <?=$i;?>
                                </option>
<?php
                                endfor;?>
                              </select>
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="4">
                              <div id="addNewNetwork" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><i class="fa fa-plus fa-fw"></i></div>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Whitelist(s)");?></td>
                    <td>
                      <table class="table table-striped table-condensed" id="whitelists_table">
                        <thead>
                          <tr>
                            <th></th>
                            <th><?=gettext("Network"); ?></th>
                            <th><?=gettext("CIDR"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
<?php
                        if (count($pconfig['whitelists_network']) == 0 ) {
                            $pconfig['whitelists_network'][] = "";
                            $pconfig['whitelists_mask'][] = "";
                        }
                        foreach($pconfig['whitelists_network'] as $item_idx => $network):?>
                          <tr>
                            <td>
                              <div style="cursor:pointer;" class="act-removerowwhitelist btn btn-default btn-xs" alt="remove"><i class="fa fa-minus fa-fw"></i></div>
                            </td>
                            <td>
                              <input name="whitelists_network[]" type="text" id="whitelist_<?=$item_idx;?>" value="<?=$network;?>" />
                            </td>
                            <td>
                              <select name="whitelists_mask[]" data-whitelist-id="whitelist_<?=$item_idx;?>" class="selectpicker ipv4v6net" id="whitelistmask<?=$item_idx;?>" data-length="3" data-width="auto">
<?php
                                for ($i = 128; $i > 0; $i--):?>
                                <option value="<?=$i;?>" <?= $pconfig['whitelists_mask'][$item_idx] == $i ?  "selected=\"selected\"" : ""?>>
                                  <?=$i;?>
                                </option>
<?php
                                endfor;?>
                              </select>
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="4">
                              <div id="addNewWhitelist" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><i class="fa fa-plus fa-fw"></i></div>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input id="submit" name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <a href="services_igmpproxy.php"><input id="cancelbutton" name="cancelbutton" type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" /></a>
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
