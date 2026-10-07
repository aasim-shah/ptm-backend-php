<template>
  <main>
    <div class="container">
      <div class="row">
        <div class="col">
          <div class="btn-group" role="group">
            <button
                type="button"
                class="btn btn-facebook"
                :key="allusers.id"
                @click="setPlaceCallValues(allusers, allusers,authuserid,channelname,agora_id)"
            >
              <i class="fa fa-phone"></i> &nbsp;
              Join Meeting {{ allusers.first_name }} &nbsp;

            </button>
          </div>
        </div>
      </div>

      <!-- Incoming Call  -->
      <div class="row my-5" v-if="incomingCall">
        <div class="col-12">
          <p>
            Incoming Call From <strong>{{ incomingCaller }}</strong>
          </p>
          <div class="btn-group" role="group">
            <button
                type="button"
                class="btn btn-danger"
                data-dismiss="modal"
                @click="declineCall"
            >
              Decline
            </button>
            <button
                type="button"
                class="btn btn-success ml-5"
                @click="acceptCall"
            >
              Accept
            </button>
          </div>
        </div>
      </div>
      <!-- End of Incoming Call  -->
    </div>

    <section id="video-container" v-if="callPlaced">
      <div id="local-video" style="border-width: 2px"></div>

      <div class="d-flex mt-1 ml-1 mb-1">
        <span class="logged-in" style="color: #00f300;">●</span>
        <p id="online_users">1</p>
      </div>

      <div class="w-100" id="remote-video"></div>
      <div class="action-btns">
        <button type="button" id="muteBtn" class="btn btn-info">
          <i id="audioIcon" class="fa fa-microphone"></i>
          <!--          {{ mutedAudio ? "Unmute" : "Mute" }}-->
        </button>
        <button
            type="button"
            id="muteVideo"
            class="btn btn-primary mx-1"
        >
          <i id="videoIcon" class="fa fa-eye"></i>
          <!--          {{ mutedVideo ? "ShowVideo" : "HideVideo" }}-->
        </button>
        <button type="button" id="endCallbtn" class="btn btn-danger">
          <i class="fa fa-ban"></i>
          EndCall
        </button>
      </div>
    </section>
  </main>
</template>

<script>
import AgoraRTC from '../AgoraRTCSDK-2.4.0';
import axios from 'axios';

export default {
  name: "AgoraChat",
  props: ["authuser", "authuserid", "allusers", "agora_id", "userId", "channelname"],
  data() {
    return {
      callPlaced: false,
      client: null,
      localStream: null,
      mutedAudio: false,
      mutedVideo: false,
      userOnlineChannel: null,
      onlineUsers: [],
      incomingCall: false,
      incomingCaller: "",
      agoraChannel: null,
    };
  },
  mounted() {

  },
  methods: {
    addVideoContainer(uid, singleJoinedUser, audio) {
      let remDiv = document.getElementById(uid);
      remDiv && remDiv.parentNode.removeChild(remDiv);

      let remoteContainer = document.getElementById("remote-video");
      let streamDiv = document.createElement("div");
      if (singleJoinedUser.length > 0) {
        let userNameLbl = document.createElement("p");
        userNameLbl.innerHTML = singleJoinedUser[0].first_name + " " + singleJoinedUser[0].last_name +"<br>";
        userNameLbl.className = singleJoinedUser[0].id;
        userNameLbl.style.padding = "4px";
        userNameLbl.style.fontSize = "12px";
        userNameLbl.style.background = "gray";
        userNameLbl.style.color = "white";
        userNameLbl.style.opacity = "0.5";
        userNameLbl.style.transform = "inherit";
        userNameLbl.style.width = "100%";
        userNameLbl.style.textAlign = "center";
        userNameLbl.style.position = "absolute";
        userNameLbl.style.zIndex = '1';
        streamDiv.appendChild(userNameLbl)

        let micI = document.createElement("i");

        if (audio === true) {
          micI.className = "mic-" + uid + " fa fa-microphone";
          micI.id = "mic-enable-" + uid;
          micI.style.bottom = "0";
          micI.style.zIndex = "1";
          micI.style.left = "3px";
          userNameLbl.appendChild(micI)
        } else {
          micI.className = "mic-" + uid + " fa fa-microphone-slash";
          micI.id = "mic-disable-" + uid;
          // micI.style.position = "absolute";
          micI.style.bottom = "0";
          micI.style.zIndex = "1";
          micI.style.left = "3px";
          userNameLbl.appendChild(micI)
        }
      }

      streamDiv.className = 'box'
      streamDiv.id = uid;                       // Assigning id to div
      streamDiv.style.transform = "rotateY(180deg)"; // Takes care of lateral inversion (mirror image)
      streamDiv.style.padding = "0"; // Takes care of lateral inversion (mirror image)
      streamDiv.style.height = "280px"; // Takes care of lateral inversion (mirror image)
      streamDiv.style.border = '2px solid rgb(0, 130, 3)';
      streamDiv.style.display = "inline-block";
      streamDiv.style.whiteSpace = "normal";
      streamDiv.style.width = "200px";
      remoteContainer.appendChild(streamDiv);      // Add new div to container
    },

    removeVideoContainer(uid, left, singleJoinedUser, audio) {
      let emptyRemoteContainer = document.getElementById("remote-video");

      if (left) {
        let remDiv = document.getElementById(uid);
        remDiv && remDiv.parentNode.removeChild(remDiv);
      } else {
        document.getElementById(uid).remove();
        let streamDiv = document.createElement("div");
        streamDiv.id = uid;
        streamDiv.className = 'box'
        streamDiv.style.padding = "0";
        streamDiv.style.height = "280px";
        streamDiv.style.background = "gray";
        streamDiv.style.display = "inline-block";
        streamDiv.style.whiteSpace = "normal";
        streamDiv.style.border = '2px solid rgb(0, 130, 3)';
        streamDiv.style.width = "200px";
        if (singleJoinedUser.length > 0) {
          let userNameLbl = document.createElement("p");
          userNameLbl.innerHTML = singleJoinedUser[0].first_name + " " + singleJoinedUser[0].last_name+"<br>";
          userNameLbl.className = singleJoinedUser[0].id;
          userNameLbl.style.padding = "4px";
          userNameLbl.style.fontSize = "12px";
          userNameLbl.style.color = "white";
          userNameLbl.style.width = "inherit";
          userNameLbl.style.transform = "inherit";
          userNameLbl.style.textAlign = "center";
          userNameLbl.style.position = "absolute";
          userNameLbl.style.zIndex = '1';
          streamDiv.appendChild(userNameLbl)
          if (audio === true) {
            let micI = document.getElementById("mic-disable-" + uid);
            if (micI === null || micI === "undefined") {
              let micI = document.createElement("i");
              micI.className = "mic-" + uid + " fa fa-microphone";
              micI.id = "mic-enable-" + uid;
              micI.style.position = "relative";
              micI.style.top = "90%";
              micI.style.zIndex = "1";
              micI.style.left = "3px";
              userNameLbl.appendChild(micI)
            } else {
              micI.className = "mic-" + uid + " fa fa-microphone";
              micI.id = "mic-enable-" + uid;
            }
          } else {
            let micI = document.getElementById("mic-enable-" + uid);
            if (micI === null || micI === "undefined") {
              let micI = document.createElement("i");
              micI.className = "mic-" + uid + " fa fa-microphone-slash";
              micI.id = "mic-disable-" + uid;
              micI.style.position = "relative";
              micI.style.top = "90%";
              micI.style.zIndex = "1";
              micI.style.left = "3px";
              userNameLbl.appendChild(micI)
            } else {
              micI.className = "mic-" + uid + " fa fa-microphone-slash";
              micI.id = "mic-disable-" + uid;
            }
          }
        }
        emptyRemoteContainer.appendChild(streamDiv);

      }
    },
    audioDisableContainer(uid) {
      let micI = document.getElementById("mic-enable-" + uid);
      if (micI === null || micI === "undefined") {
        let micI = document.createElement("i");
        let streamDiv = document.getElementById(uid).getElementsByTagName("p");
        micI.className = "mic-" + uid + " fa fa-microphone-slash";
        micI.id = "mic-disable-" + uid;
        micI.style.position = "absolute";
        micI.style.bottom = "0";
        micI.style.zIndex = "1";
        micI.style.left = "3px";
        streamDiv.appendChild(micI)
      } else {
        micI.className = "mic-" + uid + " fa fa-microphone-slash";
        micI.id = "mic-disable-" + uid;
      }
    },
    audioEnableContainer(uid) {
      let micI = document.getElementById("mic-disable-" + uid);
      if (micI === null || micI === "undefined") {
        let micI = document.createElement("i");
        let streamDiv = document.getElementById(uid).getElementsByTagName("p");
        streamDiv.getAttribute('p')
        micI.className = "mic-" + uid + " fa fa-microphone";
        micI.id = "mic-enable-" + uid;
        micI.style.position = "absolute";
        micI.style.bottom = "0";
        micI.style.zIndex = "1";
        micI.style.left = "3px";
        streamDiv.appendChild(micI)
      } else {
        micI.className = "mic-" + uid + " fa fa-microphone";
        micI.id = "mic-enable-" + uid;
      }
    },

    async setPlaceCallValues(users, calleeName, authuserid, channelname, agora_id) {
      try {
        let usersList = users.filter(function (el) {
          return el !== null;
        });
        const client = AgoraRTC.createClient({mode: "rtc", codec: "h264"});
        this.callPlaced = true;

        const [localAudioTrack, localVideoTrack] = await AgoraRTC.createMicrophoneAndCameraTracks();

        localVideoTrack.play('local-video');
        client.on("user-published", async (user, mediaType) => {
          let singleJoinedUser = usersList.filter(function (el) {
            return el.id === user.uid;
          });
          await client.subscribe(user, mediaType);
          document.getElementById('online_users').innerText = client.remoteUsers.length + 1

          if (mediaType === "video") {
            this.addVideoContainer(String(user.uid), singleJoinedUser, (!user._audio_muted_ && user._audio_added_))
            if (user._video_added_ === false) {

              this.removeVideoContainer(user.uid, false, singleJoinedUser, user._audio_enabled_) // removes the injected container
            }
            user.videoTrack.play(String(user.uid));
          }

          if (mediaType === "audio") {
            user.audioTrack.play(); // audio does not need a DOM element
            this.audioEnableContainer(user.uid) // removes the injected container

            if (user._video_added_ === false) {
              this.addVideoContainer(String(user.uid), singleJoinedUser, (!user._audio_muted_ && user._audio_added_))
              this.removeVideoContainer(user.uid, false, singleJoinedUser, user._audio_enabled_) // removes the injected container
            }

            if (user._audio_added_ === true && user._audio_enabled_ === true) {
              // this.audioEnableContainer(user.uid) // removes the injected container
            }
            if (user._audio_added_ === false && user._audio_enabled_ === false) {
              // this.audioDisableContainer(user.uid) // removes the injected container
            }

          }
        });
        client.on("user-unpublished", async (user, mediaType) => {
          let singleJoinedUser = usersList.filter(function (el) {
            return el.id === user.uid;
          });
          if (mediaType === "video") {
            this.removeVideoContainer(user.uid, false, singleJoinedUser, !user._audio_muted_) // removes the injected container
          }
          if (mediaType === "audio") {
            this.audioDisableContainer(user.uid) // removes the injected container
          }

        });
        client.on("user-left", function (evt) {
          let remDiv = document.getElementById(evt.uid);
          remDiv && remDiv.parentNode.removeChild(remDiv);
          document.getElementById('online_users').innerText = client.remoteUsers.length + 1

        });
        client.on("peer-leave", function (evt) {
        });
        client.on("user-joined", async (evt, mediaType) => {
          if (evt._video_added_ === false && evt._audio_added_ === false) {
            let singleJoinedUser = usersList.filter(function (el) {
              return el.id === evt.uid;
            });
            this.addVideoContainer(String(evt.uid), singleJoinedUser, evt._audio_added_)
            this.removeVideoContainer(evt.uid, false, singleJoinedUser, evt._audio_added_) // removes the injected container
            document.getElementById('online_users').innerText = client.remoteUsers.length + 1

          }
        });
        client.on("user-info-updated", function (user) {
        });
        client.on("peer-join", function (evt) {
        });
        client.on("mute-audio", async (evt) => {
        });
        client.on("mute-video", function (evt) {
        });
        client.on("unmute-video", function (evt) {
        });

        client.on("unmute-audio", function (evt) {
        });

        client.on("stream-published", function (evt) {
        });
        client.on("stream-added", function (evt) {
        });
        const tokenRes = await this.generateToken(channelname);
        try {
          const requestData = {
            users: users,
          };
          const response = await axios.post('/send-meeting-notification', usersList);
          console.log(response.data);
        } catch (error) {
          console.error('Error:', error);
        }
        if (tokenRes) {
          await client.join(agora_id, channelname,
              tokenRes.data,
              Number(authuserid));
          if (client.connectionState === 'CONNECTED') {
            // await client.setClientRole("host");
            await client.publish([localAudioTrack, localVideoTrack]);
            await this.endCall(client, localAudioTrack, localVideoTrack);
            await this.handleAudioToggle(client, localAudioTrack, localVideoTrack);
            await this.handleVideoToggle(client, localAudioTrack, localVideoTrack);
          }
        }

      } catch (exception) {

      }
    },
    async endCall(client, localAudioTrack, localVideoTrack) {
      const stopBtn = document.getElementById('endCallbtn');
      stopBtn.disabled = false; // Enable the stop button
      stopBtn.onclick = null; // Remove any previous event listener
      stopBtn.onclick = await function () {

        this.callPlaced = false;

        client.unpublish(); // stops sending audio & video to agora
        localVideoTrack.stop(); // stops video track and removes the player from DOM
        localVideoTrack.close(); // Releases the resource
        localAudioTrack.stop();  // stops audio track
        localAudioTrack.close(); // Releases the resource
        location.reload();
        client.remoteUsers.forEach(user => {
          if (user.hasVideo) {
            this.removeVideoContainer(user.uid, true) // Clean up DOM
          }
          client.unsubscribe(user); // unsubscribe from the user
        });
        client.removeAllListeners(); // Clean up the client object to avoid memory leaks
        location.reload();


      }
    },
    handleAudioToggle(client, localAudioTrack, localVideoTrack) {
      const muteBtn = document.getElementById('muteBtn');
      muteBtn.onclick = null;
      muteBtn.onclick = function () {
        if (this.mutedAudio) {
          localAudioTrack.setVolume(100);
          this.mutedAudio = false;
          document.getElementById("audioIcon").classList.replace('fa-microphone-slash', 'fa-microphone');
          document.getElementById('muteBtn').style.textDecoration = 'blink';
        } else {
          localAudioTrack.setVolume(0);
          this.mutedAudio = true;
          document.getElementById("audioIcon").classList.replace('fa-microphone', 'fa-microphone-slash');
          document.getElementById('muteBtn').style.textDecoration = 'line-through';
        }
      }

    },
    handleVideoToggle(client, localAudioTrack, localVideoTrack) {
      const muteVideoBtn = document.getElementById('muteVideo');
      muteVideoBtn.onclick = null;
      muteVideoBtn.onclick = function () {
        if (this.muteVideo) {
          localVideoTrack.setEnabled(true);
          this.muteVideo = false;
          document.getElementById("videoIcon").classList.replace('fa-eye-slash', 'fa-eye');
          document.getElementById('muteVideo').style.textDecoration = 'blink';
        } else {
          localVideoTrack.setEnabled(false);
          this.muteVideo = true;
          document.getElementById("videoIcon").classList.replace('fa-eye', 'fa-eye-slash');
          document.getElementById('muteVideo').style.textDecoration = 'line-through';

        }
      }
    },
    generateToken(channelName) {
      return axios.post("/agora/token", {
        channelName,
      });
    },

  },
};
</script>

<style scoped>

#video-container {
  width: 700px;
  height: 500px;
  max-width: 100vw;
  max-height: 80vh;
  margin: 0 auto;
  border: 1px solid #099dfd;
  position: absolute;
  bottom: 0;
  right: 0;
  box-shadow: 1px 1px 11px #9e9e9e;
  background-color: #fff;
}

#local-video {
  width: 30%;
  height: 30%;
  position: absolute;
  left: 10px;
  bottom: 10px;
  border: 1px solid #fff;
  border-radius: 6px;
  z-index: 2;
  cursor: pointer;
}

#remote-video {
  width: 100%;
  height: 300px;
  text-align: center;
  left: 0;
  right: 0;
  bottom: 0;
  top: 22px;
  z-index: 1;
  margin: 0;
  padding: 0;
  float: left;
  overflow-x: scroll;
  white-space: nowrap;
}

.action-btns {
  position: absolute;
  bottom: 20px;
  left: -50%;
  margin-left: -50px;
  z-index: 3;
  display: table;
  flex-direction: row;
  flex-wrap: wrap;
}


</style>